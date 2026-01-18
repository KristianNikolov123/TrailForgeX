import os
import math
import random
import time
import requests
import certifi

import osmnx as ox
import networkx as nx

from functools import lru_cache
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from dotenv import load_dotenv

# ✅ NEW: real green-area detection
from shapely.geometry import Point, LineString
import shapely.ops as ops

from typing import Optional
from pydantic import Field


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

# No disk cache (your request)
ox.settings.use_cache = False
ox.settings.log_console = False


# ----------------------------
# INPUT MODELS
# ----------------------------
class GeocodeRequest(BaseModel):
    query: str


class RouteRequest(BaseModel):
    start_lat: float
    start_lng: float
    end_lat: float | None = None
    end_lng: float | None = None
    distance_km: float
    elevation_gain_target: float | None = None  # optional
    prefer: str  # "green" | "trail" | "road"


from pydantic import BaseModel, Field, model_validator
from typing import Optional, Literal

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

    # start mode
    start_lat: Optional[float] = None
    start_lng: Optional[float] = None
    end_lat: Optional[float] = None
    end_lng: Optional[float] = None

    @model_validator(mode="after")
    def validate_by_mode(self):
        if self.mode == "loop_in_area":
            if self.center_lat is None or self.center_lng is None:
                raise ValueError("center_lat and center_lng are required for loop_in_area")
        else:
            if self.start_lat is None or self.start_lng is None:
                raise ValueError("start_lat and start_lng are required for start-based modes")
        return self



# ----------------------------
# GOOGLE ELEVATION (1 call per route)
# ----------------------------
def elevation_gain(coords):
    if not coords:
        return 0

    # downsample to <=510 points
    max_samples = 510
    if len(coords) > max_samples:
        step = max(1, len(coords) // max_samples)
        coords_down = coords[::step]
        if coords_down[-1] != coords[-1]:
            coords_down.append(coords[-1])
        coords = coords_down

    path = "|".join([f"{lat},{lng}" for lat, lng in coords])
    url = "https://maps.googleapis.com/maps/api/elevation/json"

    try:
        rv = requests.get(
            url,
            params={"path": path, "samples": len(coords), "key": GOOGLE_ELEVATION_KEY},
            timeout=25,
            verify=certifi.where(),
        )
        if rv.status_code != 200:
            return 0
        data = rv.json()
        if data.get("status") != "OK" or not data.get("results"):
            return 0

        elevs = [p["elevation"] for p in data["results"]]
        gain = 0.0
        for i in range(1, len(elevs)):
            diff = elevs[i] - elevs[i - 1]
            if diff > 0:
                gain += diff
        return int(round(gain))
    except Exception:
        return 0


# ----------------------------
# GEOCODING
# ----------------------------
@app.post("/geocode")
def geocode(req: GeocodeRequest):
    url = "https://maps.googleapis.com/maps/api/geocode/json"
    params = {"address": req.query, "key": GOOGLE_GEOCODING_KEY}
    try:
        r = requests.get(url, params=params, timeout=20, verify=certifi.where()).json()
        if r.get("status") == "OK" and r["results"]:
            location = r["results"][0]["geometry"]["location"]
            return {
                "lat": location["lat"],
                "lng": location["lng"],
                "display_name": r["results"][0].get("formatted_address", req.query),
            }
        return {"error": f"Geocoding failed: {r.get('status')} for '{req.query}'"}
    except Exception as e:
        return {"error": f"Geocoding exception: {e}"}


# ----------------------------
# RAM GRAPH CACHE (NO DISK)
# ----------------------------
def _round4(x: float) -> float:
    return round(x, 4)


@lru_cache(maxsize=10)
def get_graph_cached(center_lat: float, center_lng: float, radius_m: int):
    # rounding increases cache hits
    return ox.graph_from_point((_round4(center_lat), _round4(center_lng)), dist=radius_m, network_type="walk")


# ----------------------------
# ✅ REAL GREEN AREAS (OSM polygons) + CACHE
# ----------------------------
GREEN_TAGS = {
    "leisure": ["park", "nature_reserve", "garden"],
    "landuse": ["forest", "grass", "recreation_ground", "meadow"],
    "natural": ["wood", "grassland"],
}

@lru_cache(maxsize=10)
def get_green_union_cached(center_lat: float, center_lng: float, radius_m: int):
    """
    OSMnx v2+: use features_from_point. We union all park/green polygons.
    """
    try:
        gdf = ox.features_from_point((_round4(center_lat), _round4(center_lng)), tags=GREEN_TAGS, dist=radius_m)
        polys = gdf[gdf.geometry.type.isin(["Polygon", "MultiPolygon"])].geometry
        if polys.empty:
            return None
        return ops.unary_union(polys.values)
    except Exception:
        return None


def mark_green_edges(G, green_union):
    """
    Adds d["_green"] = 1.0 if edge geometry intersects a green polygon else 0.0
    """
    if green_union is None:
        for _, _, _, d in G.edges(keys=True, data=True):
            d["_green"] = 0.0
        return

    for u, v, k, d in G.edges(keys=True, data=True):
        geom = d.get("geometry")
        if geom is None:
            x1, y1 = G.nodes[u]["x"], G.nodes[u]["y"]
            x2, y2 = G.nodes[v]["x"], G.nodes[v]["y"]
            geom = LineString([(x1, y1), (x2, y2)])

        try:
            d["_green"] = 1.0 if geom.intersects(green_union) else 0.0
        except Exception:
            d["_green"] = 0.0


def pick_start_node(G, center_lat: float, center_lng: float, prefer: str, green_union):
    """
    In green mode, start inside a green polygon if possible, otherwise near center.
    Also randomizes the chosen node a bit so routes aren't identical.
    """
    # default: near the center
    fallback = ox.nearest_nodes(G, center_lng, center_lat)

    if prefer != "green" or green_union is None:
        # small randomization around center node
        return fallback

    green_nodes = []
    try:
        for n, nd in G.nodes(data=True):
            try:
                if Point(nd["x"], nd["y"]).within(green_union):
                    green_nodes.append(n)
            except Exception:
                continue
    except Exception:
        return fallback

    if not green_nodes:
        return fallback

    return random.choice(green_nodes)


# ----------------------------
# ROUTING WEIGHTS (mode preference)
# ----------------------------
TRAIL_HIGHWAYS = {"path", "footway", "track", "bridleway", "cycleway"}
ROAD_HIGHWAYS = {"residential", "tertiary", "secondary", "primary", "service", "unclassified", "living_street"}

TRAIL_SURFACES = {"dirt", "gravel", "ground", "unpaved", "fine_gravel", "compacted", "earth", "mud", "sand"}
PAVED_SURFACES = {"asphalt", "concrete", "paving_stones", "cobblestone", "sett", "paved"}


def edge_score(d, mode: str) -> float:
    highway = d.get("highway", "")
    if isinstance(highway, list):
        highway = highway[0] if highway else ""
    surface = (d.get("surface") or "").lower()

    # 0 = bad, 1 = good
    if mode == "trail":
        score = 0.0
        if highway in TRAIL_HIGHWAYS:
            score = 0.7
        if surface in TRAIL_SURFACES:
            score = max(score, 1.0)
        if surface in PAVED_SURFACES:
            score = min(score, 0.2)
        return score

    if mode == "road":
        score = 0.0
        if highway in ROAD_HIGHWAYS:
            score = 0.7
        if surface in PAVED_SURFACES:
            score = max(score, 1.0)
        if highway in TRAIL_HIGHWAYS and surface in TRAIL_SURFACES:
            score = min(score, 0.2)
        return score

    # ✅ GREEN: use real polygon intersection precomputed on edges
    return float(d.get("_green", 0.0))


def apply_mode_weights(G, mode: str, strength: float = 0.65, noise: float = 0.03):
    """
    Lower weight = preferred.
    weight = length * multiplier
    multiplier in roughly [1-strength, 1+strength]
    """
    for u, v, k, d in G.edges(keys=True, data=True):
        length = float(d.get("length", 1.0))
        s = edge_score(d, mode)
        mult = (1.0 + strength) - (2.0 * strength * s)
        mult *= (1.0 + random.uniform(-noise, noise))
        d["weight"] = length * mult


def route_length_m(G, route_nodes):
    total = 0.0
    for u, v in zip(route_nodes[:-1], route_nodes[1:]):
        data = G.get_edge_data(u, v)
        if not data:
            continue

        # MultiDiGraph: choose shortest edge
        if isinstance(data, dict):
            total += min(d.get("length", 0.0) for d in data.values())
        else:
            total += data.get("length", 0.0)

    return float(total)


def make_used_edge_set(route_nodes):
    used = set(zip(route_nodes, route_nodes[1:]))
    used |= set((b, a) for (a, b) in used)
    return used


# ----------------------------
# LOOP SEARCH (generate EXACTLY N candidates)
# ----------------------------
def compute_radius_loop(distance_km: float):
    if distance_km <= 0:
        return 1500

    # r ≈ dist/(2π), add slack
    r = (distance_km * 1000.0) / (2.0 * math.pi)
    r *= 2.0

    # ✅ increase cap so “green” mode can actually reach parks for 8–15km
    return int(min(max(r, 2500), 12000))


def build_loop_candidates(G, start_node: int, target_m: float, count: int = 3):
    """
    Build up to `count` candidate loops quickly.
    Strategy:
      - choose random waypoint distances around ~40-70% of target
      - go out shortest path by weight
      - come back with penalized overlap using callable weight (no G.copy)
    """
    try:
        dists = nx.single_source_dijkstra_path_length(G, start_node, weight="weight")
    except Exception:
        return []

    lo = target_m * 0.35
    hi = target_m * 0.70

    candidates = [n for n, dd in dists.items() if lo <= dd <= hi]
    if not candidates:
        candidates = sorted(dists.keys(), key=lambda n: abs(dists[n] - target_m * 0.5))[:800]

    random.shuffle(candidates)

    out = []
    tries = 0
    max_tries = 1800  # a bit higher; still bounded

    while candidates and len(out) < count and tries < max_tries:
        tries += 1
        waypoint = candidates.pop()

        try:
            out_route = nx.shortest_path(G, start_node, waypoint, weight="weight")
        except Exception:
            continue

        used = make_used_edge_set(out_route)

        def penalized_weight(u, v, d):
            w = float(d.get("weight", d.get("length", 1.0)))
            if (u, v) in used:
                return w * 5.0
            return w

        try:
            back_route = nx.shortest_path(G, waypoint, start_node, weight=penalized_weight)
        except Exception:
            continue

        full = out_route + back_route[1:]
        length_m = route_length_m(G, full)

        # keep only roughly reasonable loops (avoid tiny/huge)
        if 0.60 * target_m <= length_m <= 1.55 * target_m:
            out.append((full, length_m))

    return out


# ----------------------------
# /generate3 (returns 3 routes, pick best by elevation)
# ----------------------------
@app.post("/generate3")
def generate3(req: Generate3Request):
    start_time = time.time()

    if req.prefer not in ("green", "trail", "road"):
        req.prefer = "trail"

    # randomness per request -> different routes
    random.seed(time.time_ns())

    target_m = req.distance_km * 1000.0
    if req.mode == "loop_in_area":
        center_lat, center_lng = req.center_lat, req.center_lng
    else:
        # loop_from_start / point_to_point use start as the center for graph download
        center_lat, center_lng = req.start_lat, req.start_lng

    center = (center_lat, center_lng)
    radius = compute_radius_loop(req.distance_km)

    try:
        G0 = get_graph_cached(center[0], center[1], radius)
        G = G0.copy()
    except Exception as e:
        return {"success": False, "error": f"Could not download OSM graph: {e}", "routes": []}

    # ✅ real green polygons if prefer=green
    green_union = None
    if req.prefer == "green":
        green_union = get_green_union_cached(center[0], center[1], radius)
        mark_green_edges(G, green_union)

    apply_mode_weights(G, req.prefer, strength=0.70 if req.prefer == "green" else 0.65, noise=0.03)

    # ✅ start inside a green polygon if possible (otherwise near center)
    start_node = pick_start_node(G, center_lat, center_lng, req.prefer, green_union)


    loops = build_loop_candidates(G, start_node, target_m, count=req.n_routes)

    if not loops:
        return {"success": False, "error": "Could not build loop candidates in this area. Try different distance or center.", "routes": []}

    routes_out = []
    best_idx = 0
    best_elev = -1

    # Elevation for each candidate (<=3 API calls)
    for i, (nodes, length_m) in enumerate(loops):
        coords = [(G.nodes[n]["y"], G.nodes[n]["x"]) for n in nodes]
        elev = elevation_gain(coords)

        if elev > best_elev:
            best_elev = elev
            best_idx = i

        routes_out.append({
            "distance_km": round(length_m / 1000.0, 2),
            "elevation_gain_m": elev,
            "coordinates": coords
        })

    print(f"[TrailForgeX] generate3 done in {time.time()-start_time:.2f}s radius={radius} routes={len(routes_out)} prefer={req.prefer}")
    return {"success": True, "best_index": best_idx, "routes": routes_out}


# ----------------------------
# /generate (keeps old behavior but uses generate3 and returns BEST)
# ----------------------------
@app.post("/generate")
def generate(req: RouteRequest):
    # use start as center for loop
    if req.end_lat is not None and req.end_lng is not None:
        return {"error": "Point-to-point not supported in this simplified generator. Leave End empty for a loop."}

    g3 = Generate3Request(
        distance_km=req.distance_km,
        elevation_gain_target=req.elevation_gain_target,
        prefer=req.prefer,
        n_routes=3,
        center_lat=req.start_lat,
        center_lng=req.start_lng,
        mode="loop_in_area"
    )

    data = generate3(g3)
    if not data.get("success"):
        return {"error": data.get("error", "Failed to generate route.")}

    best = data["routes"][data["best_index"]]
    return best
