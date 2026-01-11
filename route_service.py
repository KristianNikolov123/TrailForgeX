import os
import math
import requests
import certifi
import osmnx as ox
import networkx as nx
from fastapi import FastAPI
from pydantic import BaseModel

class GeocodeRequest(BaseModel):
    query: str
from dotenv import load_dotenv

# Load .env
load_dotenv()

GOOGLE_GEOCODING_KEY = os.getenv("GOOGLE_GEOCODING_API_KEY")
GOOGLE_ELEVATION_KEY = os.getenv("GOOGLE_ELEVATION_API_KEY")

app = FastAPI()

from fastapi.middleware.cors import CORSMiddleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

ox.settings.use_cache = True
ox.settings.log_console = False

# ---------- INPUT MODEL ----------
class RouteRequest(BaseModel):
    start_lat: float
    start_lng: float
    end_lat: float | None
    end_lng: float | None
    distance_km: float
    prefer: str  # "green" | "trail" | "road"

# ---------- ELEVATION ----------
def elevation_gain(coords):
    # Google Elevation API: max samples = 512, max length ~2000 chars, must batch or downsample if needed
    max_samples = 510
    coords_down = []
    if len(coords) > max_samples:
        step = max(1, len(coords)//max_samples)
        coords_down = coords[::step]
        if coords_down[-1] != coords[-1]:
            coords_down.append(coords[-1])
    else:
        coords_down = coords
    path = "|".join([f"{lat},{lng}" for lat, lng in coords_down])

    url = "https://maps.googleapis.com/maps/api/elevation/json"
    try:
        rv = requests.get(url, params={
            "path": path,
            "samples": len(coords_down),
            "key": GOOGLE_ELEVATION_KEY
        }, timeout=35, verify=certifi.where())
        if rv.status_code != 200:
            return 0
        try:
            r = rv.json()
        except Exception:
            return 0
        if r.get('status') != 'OK' or "results" not in r or not r["results"]:
            return 0
        elevs = [p["elevation"] for p in r["results"]]
        gain = 0
        for i in range(1, len(elevs)):
            diff = elevs[i] - elevs[i-1]
            if diff > 0:
                gain += diff
        return round(gain)
    except Exception:
        return 0

# ---------- GEOCODING ----------
@app.post("/geocode")
def geocode(req: GeocodeRequest):
    """Geocode any address using the Google Geocoding API (no hardcoded logic!)"""
    url = "https://maps.googleapis.com/maps/api/geocode/json"
    params = {"address": req.query, "key": GOOGLE_GEOCODING_KEY}
    try:
        r = requests.get(url, params=params, timeout=20, verify=certifi.where()).json()
        if r.get("status") == "OK" and r["results"]:
            location = r["results"][0]["geometry"]["location"]
            return {
                "lat": location["lat"],
                "lng": location["lng"],
                "display_name": r["results"][0].get("formatted_address", req.query)
            }
        else:
            return {"error": f"Geocoding failed: {r.get('status')} for '{req.query}'"}
    except Exception as e:
        return {"error": f"Geocoding exception: {e}"}

# ---------- ROUTE GENERATION ----------
def get_route_distance(G, route):
    total_length = 0.0
    for i in range(len(route) - 1):
        u, v = route[i], route[i + 1]
        edge_data = G.get_edge_data(u, v)
        if edge_data:
            # Handles MultiGraph (multiple edges possible)
            if isinstance(edge_data, dict) and hasattr(edge_data, 'values'):
                lengths = [d.get('length', 0) for d in edge_data.values()]
                total_length += min(lengths)
            else:
                total_length += edge_data.get('length', 0)
    return total_length

@app.post("/generate")
def generate_route(req: RouteRequest):
    import time
    start_time = time.time()
    print(f"[TrailForgeX] Route request: {req}")
    center = (req.start_lat, req.start_lng)

    print(f"[TrailForgeX] Downloading graph from OSM...")
    if req.end_lat is not None and (abs(req.start_lat - req.end_lat) > 0.08 or abs(req.start_lng - req.end_lng) > 0.08):
        # Restrict area: prevent start/end that are not in the same region (~8-10km)
        err = f"Start/end too far apart! Please pick locations in the same area."
        print(f"[TrailForgeX] {err}")
        return {"error": err}
    # ----- Optimized OSM download bounding box -----
    from geopy.distance import geodesic
    if req.end_lat is not None:
        # Adaptive bounding: midpoint and minimal radius for A->B
        center_lat = (req.start_lat + req.end_lat) / 2
        center_lng = (req.start_lng + req.end_lng) / 2
        base_distance = geodesic((req.start_lat, req.start_lng), (req.end_lat, req.end_lng)).meters
        radius = max(base_distance / 2 + 800, req.distance_km * 1000 * 0.75)
        center = (center_lat, center_lng)
    else:
        # Loops: center at start, TIGHT RADIUS, for city/road density
        radius = min(req.distance_km * 480, 2200)  # max 2200m for up to 10km loop
        center = (req.start_lat, req.start_lng)
    radius = min(radius, 2200)
    try:
        G = ox.graph_from_point(
            center,
            dist=radius,
            network_type="walk"
        )
    except Exception as e:
        err = f"Error: Could not download map data: {e}"
        print(f"[TrailForgeX] {err}")
        return {"error": err}
    print(f"[TrailForgeX] Graph download complete: {len(G.nodes)} nodes, {len(G.edges)} edges, took {time.time() - start_time:.2f}s")

    # Prefer surfaces
    for u, v, k, d in G.edges(keys=True, data=True):
        highway = d.get("highway", "")
        surface = d.get("surface", "")
        penalty = 1.0
        if req.prefer == "green":
            if highway not in ["footway", "path"]:
                penalty = 1.5
        elif req.prefer == "trail":
            if surface not in ["dirt", "gravel", "ground"]:
                penalty = 1.7
        elif req.prefer == "road":
            if highway in ["footway", "path"]:
                penalty = 1.3
        d["weight"] = d["length"] * penalty
    print(f"[TrailForgeX] Edge weight assignment completed in {time.time() - start_time:.2f}s")

    start_node = ox.nearest_nodes(G, req.start_lng, req.start_lat)
    if req.end_lat is not None:
        end_node = ox.nearest_nodes(G, req.end_lng, req.end_lat)
        print(f"[TrailForgeX] Nodes: start={start_node} end={end_node}")
        route = nx.shortest_path(G, start_node, end_node, weight="weight")
    else:
        # Find nodes that give ~50% of target (halfway); assemble a full loop within ±5%
        dists = nx.single_source_dijkstra_path_length(G, start_node, weight="weight")
        goal_dist = req.distance_km * 1000 / 2
        lower = goal_dist * 0.95
        upper = goal_dist * 1.05
        candidates = [node for node, d in dists.items() if lower <= d <= upper][:100]
        print(f"[TrailForgeX] {len(candidates)} candidates for half-loop found in ±5% range (max 100 considered).")
        best_route = None
        best_error = float('inf')
        for candidate in candidates:
            try:
                out_route = nx.shortest_path(G, start_node, candidate, weight="weight")
                back_route = nx.shortest_path(G, candidate, start_node, weight="weight")
                full_route = out_route + back_route[1:]
                total_length = get_route_distance(G, full_route)
                error = abs(total_length - req.distance_km * 1000)
                if error < best_error:
                    best_error = error
                    best_route = full_route
            except Exception as e:
                continue
        if best_route is not None:
            print(f"[TrailForgeX] Loop mode: picked candidate node with error ±{best_error:.2f} m.")
            route = best_route
        else:
            # fallback to closest node (old behavior)
            best_node = min(dists.items(), key=lambda x: abs(x[1] - goal_dist))[0]
            print(f"[TrailForgeX] Loop mode fallback: best halfway node {best_node}, dist {dists[best_node]:.2f} m")
            route_out = nx.shortest_path(G, start_node, best_node, weight="weight")
            route_back = nx.shortest_path(G, best_node, start_node, weight="weight")
            route = route_out + route_back[1:]
    print(f"[TrailForgeX] Route with {len(route)} steps built.")

    coords = [(G.nodes[n]["y"], G.nodes[n]["x"]) for n in route]

    distance = get_route_distance(G, route) / 1000
    print(f"[TrailForgeX] Distance calculation done: {distance:.2f} km, elapsed {time.time() - start_time:.2f}s")

    print(f"[TrailForgeX] Calling Google Elevation API for {len(coords)} points...")
    elevation = elevation_gain(coords)
    print(f"[TrailForgeX] Elevation gain: {elevation:.2f} m, total elapsed {time.time() - start_time:.2f}s")
    
    if distance > req.distance_km * 3:
        # Sanity check, if something went wrong in route planning
        return {"error": "Generated route is unreasonably long. Try a smaller distance or check your locations."}

    return {
        "distance_km": round(distance, 2),
        "elevation_gain_m": elevation,
        "coordinates": coords
    }

    