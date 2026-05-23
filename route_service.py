import os
import math
import random
import time
import threading
import requests
import certifi

import osmnx as ox
import networkx as nx

from functools import lru_cache
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field, model_validator
from dotenv import load_dotenv
from typing import Optional, Literal

from shapely.geometry import Point, LineString
import shapely.ops as ops

# ----------------------------
# ENV / APP SETUP
# ----------------------------
load_dotenv()

GOOGLE_GEOCODING_KEY = os.getenv("GOOGLE_GEOCODING_API_KEY")
GOOGLE_ELEVATION_KEY = os.getenv("GOOGLE_ELEVATION_API_KEY")

app = FastAPI()
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost", "http://localhost:80", "http://127.0.0.1", "http://127.0.0.1:80", "*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

ox.settings.use_cache = False
ox.settings.log_console = False

# Thread lock — lru_cache is not thread-safe under concurrent FastAPI requests
_graph_lock = threading.Lock()


# ----------------------------
# INPUT MODELS
# ----------------------------
class GeocodeRequest(BaseModel):
    query: str


class RouteRequest(BaseModel):
    start_lat: float
    start_lng: float
    end_lat: Optional[float] = None
    end_lng: Optional[float] = None
    distance_km: float
    elevation_gain_target: Optional[float] = None
    prefer: str


Mode = Literal["loop_in_area", "loop_from_start", "point_to_point"]


class Generate3Request(BaseModel):
    distance_km: float
    elevation_gain_target: Optional[float] = None
    prefer: str = "green"
    n_routes: int = Field(default=3, ge=1, le=3)
    mode: Mode = "loop_in_area"

    # area mode
    center_lat: Optional[float] = None
    center_lng: Optional[float] = None

    # start/p2p mode
    start_lat: Optional[float] = None
    start_lng: Optional[float] = None
    end_lat: Optional[float] = None
    end_lng: Optional[float] = None

    @model_validator(mode="after")
    def validate_by_mode(self):
        if self.mode == "loop_in_area":
            if self.center_lat is None or self.center_lng is None:
                raise ValueError("center_lat and center_lng required for loop_in_area")
        else:
            if self.start_lat is None or self.start_lng is None:
                raise ValueError("start_lat and start_lng required for start-based modes")
        return self


# ----------------------------
# GEOCODING
# ----------------------------
@app.post("/geocode")
def geocode(req: GeocodeRequest):
    url = "https://maps.googleapis.com/maps/api/geocode/json"
    try:
        r = requests.get(
            url,
            params={"address": req.query, "key": GOOGLE_GEOCODING_KEY},
            timeout=20,
            verify=certifi.where(),
        ).json()
        if r.get("status") == "OK" and r["results"]:
            loc = r["results"][0]["geometry"]["location"]
            return {
                "lat": loc["lat"],
                "lng": loc["lng"],
                "display_name": r["results"][0].get("formatted_address", req.query),
            }
        return {"error": f"Geocoding failed: {r.get('status')} for '{req.query}'"}
    except Exception as e:
        return {"error": f"Geocoding exception: {e}"}


# ----------------------------
# GRAPH CACHE  (thread-safe)
# ----------------------------
def _round3(x: float) -> float:
    """~111m precision — maximises cache hits without losing meaningful accuracy."""
    return round(x, 3)


@lru_cache(maxsize=12)
def _get_graph_inner(lat: float, lng: float, radius_m: int) -> nx.MultiDiGraph:
    return ox.graph_from_point((lat, lng), dist=radius_m, network_type="walk")


def get_graph_cached(lat: float, lng: float, radius_m: int) -> nx.MultiDiGraph:
    with _graph_lock:
        return _get_graph_inner(_round3(lat), _round3(lng), radius_m)


# ----------------------------
# GREEN AREA CACHE
# ----------------------------
GREEN_TAGS = {
    "leisure": ["park", "nature_reserve", "garden"],
    "landuse": ["forest", "grass", "recreation_ground", "meadow"],
    "natural": ["wood", "grassland"],
}


@lru_cache(maxsize=12)
def get_green_union_cached(lat: float, lng: float, radius_m: int):
    try:
        gdf = ox.features_from_point((lat, lng), tags=GREEN_TAGS, dist=radius_m)
        polys = gdf[gdf.geometry.type.isin(["Polygon", "MultiPolygon"])].geometry
        return None if polys.empty else ops.unary_union(polys.values)
    except Exception:
        return None


def mark_green_edges(G, green_union):
    """Pre-compute _green flag on every edge so edge_score() is O(1) later."""
    if green_union is None:
        for _, _, _, d in G.edges(keys=True, data=True):
            d["_green"] = 0.0
        return
    for u, v, k, d in G.edges(keys=True, data=True):
        geom = d.get("geometry")
        if geom is None:
            geom = LineString([(G.nodes[u]["x"], G.nodes[u]["y"]),
                               (G.nodes[v]["x"], G.nodes[v]["y"])])
        try:
            d["_green"] = 1.0 if geom.intersects(green_union) else 0.0
        except Exception:
            d["_green"] = 0.0


# ----------------------------
# HAVERSINE + BEARING UTILS
# ----------------------------
def haversine_m(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    R = 6_371_000
    phi1, phi2 = math.radians(lat1), math.radians(lat2)
    a = (math.sin(math.radians(lat2 - lat1) / 2) ** 2
         + math.cos(phi1) * math.cos(phi2)
         * math.sin(math.radians(lng2 - lng1) / 2) ** 2)
    return 2 * R * math.asin(min(1.0, math.sqrt(a)))


def offset_point(lat: float, lng: float, bearing_deg: float, distance_m: float):
    """
    Returns (lat, lng) displaced from (lat, lng) by distance_m
    along bearing_deg (0 = north, clockwise).
    Uses the standard spherical-Earth formula — no degree-approximation shortcut.
    """
    R = 6_371_000
    b = math.radians(bearing_deg)
    lat1 = math.radians(lat)
    lng1 = math.radians(lng)
    lat2 = math.asin(
        math.sin(lat1) * math.cos(distance_m / R)
        + math.cos(lat1) * math.sin(distance_m / R) * math.cos(b)
    )
    lng2 = lng1 + math.atan2(
        math.sin(b) * math.sin(distance_m / R) * math.cos(lat1),
        math.cos(distance_m / R) - math.sin(lat1) * math.sin(lat2),
    )
    return math.degrees(lat2), math.degrees(lng2)


# ----------------------------
# GREEN-AWARE START NODE
# ----------------------------
def pick_start_node(G, center_lat, center_lng, prefer, green_union, max_offset_m=400):
    """
    For green mode: pick a node inside a green polygon close to center.
    Uses proper haversine — not the broken degree-squared approximation.
    Falls back to nearest node if nothing is found.
    """
    fallback = ox.nearest_nodes(G, center_lng, center_lat)
    if prefer != "green" or green_union is None:
        return fallback

    green_nodes = [
        n for n, nd in G.nodes(data=True)
        if haversine_m(center_lat, center_lng, nd["y"], nd["x"]) <= max_offset_m
        and _safe_within(Point(nd["x"], nd["y"]), green_union)
    ]
    return random.choice(green_nodes) if green_nodes else fallback


def _safe_within(pt, polygon):
    try:
        return pt.within(polygon)
    except Exception:
        return False


# ----------------------------
# EDGE WEIGHTS
# ----------------------------
TRAIL_HIGHWAYS  = {"path", "footway", "track", "bridleway", "cycleway"}
ROAD_HIGHWAYS   = {"residential", "tertiary", "secondary", "primary",
                   "service", "unclassified", "living_street"}
TRAIL_SURFACES  = {"dirt", "gravel", "ground", "unpaved", "fine_gravel",
                   "compacted", "earth", "mud", "sand"}
PAVED_SURFACES  = {"asphalt", "concrete", "paving_stones", "cobblestone", "sett", "paved"}


def edge_score(d, mode: str) -> float:
    hw = d.get("highway", "")
    if isinstance(hw, list):
        hw = hw[0] if hw else ""
    surf = (d.get("surface") or "").lower()

    if mode == "trail":
        s = 0.7 if hw in TRAIL_HIGHWAYS else 0.0
        if surf in TRAIL_SURFACES: s = max(s, 1.0)
        if surf in PAVED_SURFACES: s = min(s, 0.2)
        return s

    if mode == "road":
        s = 0.7 if hw in ROAD_HIGHWAYS else 0.0
        if surf in PAVED_SURFACES: s = max(s, 1.0)
        if hw in TRAIL_HIGHWAYS and surf in TRAIL_SURFACES: s = min(s, 0.2)
        return s

    # green: use precomputed polygon flag
    return float(d.get("_green", 0.0))


def apply_mode_weights(G, mode: str, strength: float = 0.70, noise: float = 0.12):
    """
    Noise raised from 3 % → 12 % so the 3 candidates generated per
    request are meaningfully different from each other.
    """
    for u, v, k, d in G.edges(keys=True, data=True):
        length = float(d.get("length", 1.0))
        s = edge_score(d, mode)
        mult = (1.0 + strength) - (2.0 * strength * s)
        mult *= (1.0 + random.uniform(-noise, noise))
        d["weight"] = length * mult


# ----------------------------
# ROUTE HELPERS
# ----------------------------
def route_length_m(G, nodes) -> float:
    total = 0.0
    for u, v in zip(nodes[:-1], nodes[1:]):
        data = G.get_edge_data(u, v)
        if not data:
            continue
        if isinstance(data, dict):
            total += min(dd.get("length", 0.0) for dd in data.values())
        else:
            total += data.get("length", 0.0)
    return total


def make_used_edge_set(nodes):
    used = set(zip(nodes, nodes[1:]))
    used |= {(b, a) for a, b in used}
    return used


def route_fingerprint(nodes, bins: int = 20) -> tuple:
    """Cheap dedup: sample ~20 evenly-spaced nodes."""
    if not nodes:
        return ()
    step = max(1, len(nodes) // bins)
    return tuple(nodes[::step])


def compute_radius(distance_km: float) -> int:
    """
    A loop of D km sits in a circle of radius ≈ D/π km.
    We apply a 1.7× slack factor so detours and green areas are reachable.
    Previous code used 2× but also capped too low (12 km), cutting off
    longer routes. New cap is 16 km.
    """
    r = (distance_km * 1000.0 / math.pi) * 1.7
    return int(min(max(r, 2500), 16_000))


# ----------------------------
# BATCHED ELEVATION  (1 API call for all routes)
# ----------------------------
def elevation_gain_batch(all_coords_list: list[list]) -> list[int]:
    """
    Fetches elevation for all candidate routes in a SINGLE Google API call
    (or two if the total sample count exceeds 512).

    Old code made up to 3 separate calls; this makes at most ceil(N/512).
    """
    MAX_PER_ROUTE = 100   # sample points per route
    MAX_LOCATIONS = 512   # Google hard limit per request

    route_ranges: list = []
    flat: list = []

    for coords in all_coords_list:
        if not coords:
            route_ranges.append(None)
            continue
        n = len(coords)
        step = max(1, n // MAX_PER_ROUTE)
        sampled = coords[::step]
        if sampled[-1] != coords[-1]:
            sampled = sampled + [coords[-1]]
        start = len(flat)
        flat.extend(sampled)
        route_ranges.append((start, len(flat)))

    if not flat:
        return [0] * len(all_coords_list)

    # ---- one (or two) HTTP requests ----
    all_elevs: list[float] = []
    for i in range(0, len(flat), MAX_LOCATIONS):
        batch = flat[i : i + MAX_LOCATIONS]
        loc_str = "|".join(f"{lat},{lng}" for lat, lng in batch)
        try:
            rv = requests.get(
                "https://maps.googleapis.com/maps/api/elevation/json",
                params={"locations": loc_str, "key": GOOGLE_ELEVATION_KEY},
                timeout=25,
                verify=certifi.where(),
            )
            data = rv.json()
            if data.get("status") == "OK":
                all_elevs.extend(r["elevation"] for r in data["results"])
            else:
                all_elevs.extend([0.0] * len(batch))
        except Exception:
            all_elevs.extend([0.0] * len(batch))

    # ---- compute gain per route ----
    results: list[int] = []
    for info in route_ranges:
        if info is None:
            results.append(0)
            continue
        s, e = info
        elevs = all_elevs[s:e]
        gain = sum(max(0.0, elevs[j] - elevs[j - 1]) for j in range(1, len(elevs)))
        results.append(int(round(gain)))

    return results


# ----------------------------
# ANGULAR-SECTOR LOOP BUILDER
# ----------------------------
def build_loop_candidates(
    G,
    start_node: int,
    start_lat: float,
    start_lng: float,
    target_m: float,
    count: int = 3,
) -> list:
    """
    Generates `count` structurally distinct loops by placing the turning
    waypoint in evenly-spaced compass sectors.

    Each sector gets multiple distance attempts so a single bad distance
    no longer silently drops a route — old code only tried once per sector,
    which caused 2-route results whenever one quality gate check failed.
    """
    # Rotate sector grid per request for variety
    base_offset = random.uniform(0, 360 / count)
    sector_bearings = [(base_offset + i * 360 / count) % 360 for i in range(count)]

    # Multiple distance fractions to try within each sector (near→far).
    # If the first distance fails the quality gate or path search, we retry
    # with the next one before giving up on the sector entirely.
    DIST_FRACS = [0.48, 0.43, 0.54, 0.38, 0.60, 0.35, 0.65]

    candidates: list = []
    seen_fps: set = set()

    # Two-pass quality gates.
    # Pass 1: tight (75–125 %) — gives routes close to the requested distance.
    # Pass 2: loose (65–140 %) — fallback for sectors where the street grid
    #          makes a tight-fit route impossible (dense areas, dead-ends, etc.).
    # A sector only advances to pass 2 if pass 1 found nothing for it.
    GATES = [
        (0.75, 1.25),   # pass 1 — tight
        (0.65, 1.40),   # pass 2 — loose fallback
    ]

    for gate_lo, gate_hi in GATES:
        for bearing in sector_bearings:
            # Skip sectors that already have a candidate from an earlier pass
            sector_idx = sector_bearings.index(bearing)
            if len(candidates) > sector_idx:
                continue

            for dist_frac in DIST_FRACS:
                wp_dist = target_m * dist_frac
                wp_lat, wp_lng = offset_point(start_lat, start_lng, bearing, wp_dist)

                try:
                    waypoint = ox.nearest_nodes(G, wp_lng, wp_lat)
                except Exception:
                    continue

                if waypoint == start_node:
                    continue

                try:
                    out = nx.shortest_path(G, start_node, waypoint, weight="weight")
                except Exception:
                    continue

                used = make_used_edge_set(out)

                def penalized(u, v, d, _used=used):
                    w = float(d.get("weight", d.get("length", 1.0)))
                    return w * 2.5 if (u, v) in _used else w

                try:
                    back = nx.shortest_path(G, waypoint, start_node, weight=penalized)
                except Exception:
                    continue

                full = out + back[1:]
                length_m = route_length_m(G, full)

                if not (gate_lo * target_m <= length_m <= gate_hi * target_m):
                    continue

                fp = route_fingerprint(full)
                if fp in seen_fps:
                    continue
                seen_fps.add(fp)

                candidates.append((full, length_m))
                break  # found one for this sector, move on

        if len(candidates) >= count:
            break  # all sectors filled, no need for the loose pass

    return candidates


# ----------------------------
# ANGULAR-SECTOR P2P BUILDER
# ----------------------------
def build_point_to_point_candidates(
    G,
    start_node: int,
    end_node: int,
    start_lat: float,
    start_lng: float,
    end_lat: float,
    end_lng: float,
    target_m: float,
    count: int = 3,
) -> list:
    """
    Builds diverse P2P routes by swinging detours left, centre, right of
    the start→end bearing instead of randomising waypoints from a flat list.
    """
    bearing = math.degrees(
        math.atan2(end_lng - start_lng, end_lat - start_lat)
    ) % 360

    # Left / straight / right relative to the start→end direction
    offsets = [-70, 0, 70]
    detour_dist = target_m * 0.32

    candidates: list = []
    seen: set = set()

    for off in offsets:
        angle = (bearing + off) % 360
        dist = detour_dist * (0.5 if off == 0 else 1.0)
        wp_lat, wp_lng = offset_point(start_lat, start_lng, angle, dist)

        try:
            wp_node = ox.nearest_nodes(G, wp_lng, wp_lat)
        except Exception:
            continue

        try:
            p1 = nx.shortest_path(G, start_node, wp_node, weight="weight")
            p2 = nx.shortest_path(G, wp_node, end_node, weight="weight")
        except Exception:
            continue

        full = p1 + p2[1:]
        length_m = route_length_m(G, full)

        if not (0.75 * target_m <= length_m <= 1.25 * target_m):
            continue

        fp = route_fingerprint(full)
        if fp in seen:
            continue
        seen.add(fp)

        candidates.append((full, length_m))

    # Fallback: direct route if all detours failed
    if not candidates:
        try:
            direct = nx.shortest_path(G, start_node, end_node, weight="weight")
            candidates.append((direct, route_length_m(G, direct)))
        except Exception:
            pass

    return candidates[:count]


# ----------------------------
# BEST ROUTE SELECTOR
# ----------------------------
def pick_best_index(routes: list, elevation_target: Optional[float]) -> int:
    """
    Old code always picked highest elevation.
    Now: if the user set an elevation target, pick the route closest to it;
    otherwise fall back to highest elevation.
    """
    if not routes:
        return 0
    if elevation_target and elevation_target > 0:
        return min(range(len(routes)),
                   key=lambda i: abs(routes[i]["elevation_gain_m"] - elevation_target))
    return max(range(len(routes)), key=lambda i: routes[i]["elevation_gain_m"])


# ----------------------------
# /generate3
# ----------------------------
@app.post("/generate3")
def generate3(req: Generate3Request):
    t0 = time.time()

    if req.prefer not in ("green", "trail", "road"):
        req.prefer = "trail"

    random.seed(time.time_ns())  # fresh randomness per request

    target_m = req.distance_km * 1000.0

    if req.mode == "loop_in_area":
        center_lat, center_lng = req.center_lat, req.center_lng
    else:
        center_lat, center_lng = req.start_lat, req.start_lng

    radius = compute_radius(req.distance_km)

    # ---- download / fetch graph ----
    try:
        G0 = get_graph_cached(center_lat, center_lng, radius)
        G = G0.copy()
    except Exception as e:
        return {"success": False, "error": f"Could not download OSM graph: {e}", "routes": []}

    # ---- green polygons ----
    green_union = None
    if req.prefer == "green":
        green_union = get_green_union_cached(_round3(center_lat), _round3(center_lng), radius)
        mark_green_edges(G, green_union)

    apply_mode_weights(G, req.prefer, strength=0.70, noise=0.12)

    # ---- start node ----
    start_node = pick_start_node(G, center_lat, center_lng, req.prefer, green_union)
    sn = G.nodes[start_node]
    start_lat_actual, start_lng_actual = sn["y"], sn["x"]

    # ---- build candidates ----
    if req.mode == "point_to_point":
        if req.end_lat is None or req.end_lng is None:
            return {"success": False, "error": "end_lat/end_lng required for point_to_point", "routes": []}
        end_node = ox.nearest_nodes(G, req.end_lng, req.end_lat)
        candidates = build_point_to_point_candidates(
            G, start_node, end_node,
            start_lat_actual, start_lng_actual,
            req.end_lat, req.end_lng,
            target_m, count=req.n_routes,
        )
    else:
        candidates = build_loop_candidates(
            G, start_node,
            start_lat_actual, start_lng_actual,
            target_m, count=req.n_routes,
        )

    if not candidates:
        return {
            "success": False,
            "error": (
                f"Could not build route candidates for {req.distance_km} km in this area. "
                "Try a different location, distance, or preference."
            ),
            "routes": [],
        }

    # ---- batch elevation (ONE API call) ----
    all_coords = [
        [(G.nodes[n]["y"], G.nodes[n]["x"]) for n in nodes]
        for nodes, _ in candidates
    ]
    all_elevations = elevation_gain_batch(all_coords)

    routes_out = [
        {
            "distance_km": round(length_m / 1000.0, 2),
            "elevation_gain_m": elev,
            "coordinates": coords,
        }
        for ((_nodes, length_m), elev, coords) in zip(candidates, all_elevations, all_coords)
    ]

    best_idx = pick_best_index(routes_out, req.elevation_gain_target)

    print(
        f"[TrailForgeX] generate3 {time.time()-t0:.2f}s | "
        f"radius={radius}m | routes={len(routes_out)} | "
        f"prefer={req.prefer} | mode={req.mode}"
    )

    return {"success": True, "best_index": best_idx, "routes": routes_out}


# ----------------------------
# /generate  (legacy — delegates to generate3)
# ----------------------------
@app.post("/generate")
def generate(req: RouteRequest):
    mode = "point_to_point" if (req.end_lat is not None and req.end_lng is not None) else "loop_from_start"
    g3 = Generate3Request(
        distance_km=req.distance_km,
        elevation_gain_target=req.elevation_gain_target,
        prefer=req.prefer,
        n_routes=3,
        start_lat=req.start_lat,
        start_lng=req.start_lng,
        end_lat=req.end_lat,
        end_lng=req.end_lng,
        mode=mode,
    )
    data = generate3(g3)
    if not data.get("success"):
        return {"error": data.get("error", "Failed to generate route.")}
    return data["routes"][data["best_index"]]