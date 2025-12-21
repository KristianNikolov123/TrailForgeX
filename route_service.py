import os
import math
import requests
import osmnx as ox
import networkx as nx
import random
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from dotenv import load_dotenv
from typing import Optional

# Load .env
load_dotenv()

GOOGLE_KEY = os.getenv("GOOGLE_ELEVATION_API_KEY")

app = FastAPI()

# Enable CORS for frontend
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
    end_lat: Optional[float] = None
    end_lng: Optional[float] = None
    distance_km: float
    elevation_gain_target: Optional[float] = None
    prefer: str  # "green" | "trail" | "road"

class GeocodeRequest(BaseModel):
    query: str

# ---------- GEOCODING ----------
@app.post("/geocode")
def geocode(req: GeocodeRequest):
    """Geocode an address using Nominatim (OSM)"""
    url = "https://nominatim.openstreetmap.org/search"
    
    # Clean and normalize the query
    query_clean = req.query.strip()
    
    # Try multiple query variations for better geocoding success
    queries_to_try = []
    
    # Detect if query contains Cyrillic characters (Bulgarian address)
    has_cyrillic = any(ord(char) > 127 for char in query_clean)
    
    # Build query variations
    queries_to_try.append(query_clean)  # Original query first
    
    # Special handling for "ул. Витоша" / "Vitosha" addresses
    if "витоша" in query_clean.lower() or "vitosha" in query_clean.lower():
        queries_to_try.extend([
            "Vitosha Boulevard 1, Sofia, Bulgaria",
            "бул. Витоша 1, София, България",
            "Vitosha 1, Sofia",
            "Витоша 1, София",
            "Vitosha Blvd, Sofia",
        ])
    
    # If it looks like a street address (contains numbers), try variations
    if any(char.isdigit() for char in query_clean):
        # Try with common address prefixes
        if not query_clean.lower().startswith(("ул.", "улица", "бул.", "булевард", "street", "st.", "str.", "boulevard", "blvd")):
            queries_to_try.extend([
                f"ул. {query_clean}",
                f"улица {query_clean}",
                f"{query_clean} Street",
            ])
    
    # Add location context (city/country) if not already present
    if "sofia" not in query_clean.lower() and "софия" not in query_clean.lower():
        if has_cyrillic:
            queries_to_try.extend([
                f"{query_clean}, София, България",
                f"{query_clean}, София",
            ])
        queries_to_try.extend([
            f"{query_clean}, Sofia, Bulgaria",
            f"{query_clean}, Sofia",
        ])
    
    # If no country specified, add Bulgaria
    if "bulgaria" not in query_clean.lower() and "българия" not in query_clean.lower():
        queries_to_try.append(f"{query_clean}, Bulgaria")
    
    # Remove duplicates while preserving order
    seen = set()
    queries_to_try = [q for q in queries_to_try if q not in seen and not seen.add(q)]
    
    headers = {
        "User-Agent": "TrailForgeX/1.0 (trailforgex@example.com)",
        "Accept-Language": "en-US,en;q=0.9,bg;q=0.8"
    }
    
    last_error = None
    import time
    
    for query in queries_to_try:
        try:
            params = {
                "q": query,
                "format": "json",
                "limit": 10,
                "addressdetails": 1,
                "countrycodes": "bg",
                "accept-language": "en,bg"
            }
            
            # Increase timeout for Cyrillic queries which may take longer
            timeout_val = 20 if has_cyrillic else 15
            
            try:
                response = requests.get(url, params=params, headers=headers, timeout=timeout_val)
            except requests.exceptions.Timeout:
                last_error = "Request timeout"
                print(f"Timeout for query '{query}', trying next variation...")
                continue
            
            # Handle rate limiting and service unavailable with retries
            if response.status_code == 429:
                # Rate limited - wait and retry
                wait_time = 2
                print(f"Rate limited (429) for query '{query}', waiting {wait_time}s...")
                time.sleep(wait_time)
                # Retry once
                try:
                    response = requests.get(url, params=params, headers=headers, timeout=timeout_val)
                except requests.exceptions.Timeout:
                    last_error = "Request timeout"
                    continue
            
            if response.status_code == 503:
                # Service unavailable - wait and retry
                wait_time = 3
                print(f"Service unavailable (503) for query '{query}', waiting {wait_time}s...")
                time.sleep(wait_time)
                # Retry once
                try:
                    response = requests.get(url, params=params, headers=headers, timeout=timeout_val)
                except requests.exceptions.Timeout:
                    last_error = "Request timeout"
                    continue
                if response.status_code == 503:
                    last_error = "Nominatim service temporarily unavailable (503)"
                    continue
            
            if response.status_code != 200:
                last_error = f"HTTP {response.status_code}"
                continue
            
            data = response.json()
            
            if data and len(data) > 0:
                # Find best match using scoring
                result = None
                best_score = 0
                
                for item in data:
                    score = 0
                    address = item.get("address", {})
                    display_name = item.get("display_name", "").lower()
                    
                    # Score based on relevance - generic scoring for any address
                    query_lower = query_clean.lower()
                    
                    # Prefer results with house numbers if query contains a number
                    if any(char.isdigit() for char in query_lower):
                        house_num = address.get("house_number", "")
                        if house_num and house_num in query_lower:
                            score += 15  # Exact house number match
                        elif address.get("house_number"):
                            score += 5  # Has house number
                    
                    # Prefer results in the same city/country
                    city = address.get("city", "").lower() or address.get("town", "").lower()
                    if city and (city in query_lower or "sofia" in query_lower or "софия" in query_lower):
                        score += 8
                    if address.get("country_code", "").lower() == "bg" or "bulgaria" in query_lower or "българия" in query_lower:
                        score += 5
                    
                    # Prefer results that match street name (check for key words)
                    street_name = address.get("road", "").lower() or address.get("street", "").lower()
                    if street_name:
                        # Check if street name contains words from query
                        query_words = [w for w in query_lower.split() if len(w) > 3 and w not in ["sofia", "bulgaria", "софия", "българия"]]
                        if query_words and any(word in street_name for word in query_words):
                            score += 10
                    
                    # Prefer results with more complete address information
                    if address.get("postcode"):
                        score += 2
                    if address.get("suburb") or address.get("neighbourhood"):
                        score += 1
                    
                    if score > best_score:
                        best_score = score
                        result = item
                
                # If no good match found, use first result
                if not result:
                    result = data[0]
                
                return {
                    "lat": float(result["lat"]),
                    "lng": float(result["lon"]),
                    "display_name": result.get("display_name", req.query)
                }
        except requests.exceptions.Timeout:
            last_error = "Request timeout"
            continue
        except requests.exceptions.RequestException as e:
            last_error = str(e)
            continue
        except Exception as e:
            last_error = str(e)
            continue
    
    # If all queries failed, try a broader search without house number
    try:
        # Try searching for street name without house number
        if "витоша" in query_clean.lower() or "vitosha" in query_clean.lower():
            fallback_queries = [
                "Vitosha Boulevard, Sofia, Bulgaria",
                "бул. Витоша, София, България",
                "Vitosha, Sofia"
            ]
        elif "rayko" in query_clean.lower():
            fallback_queries = ["Rayko Alexiev, Sofia, Bulgaria"]
        else:
            # Generic fallback - extract street name
            fallback_queries = [query_clean]
        
        for fallback_query in fallback_queries:
            params = {
                "q": fallback_query,
                "format": "json",
                "limit": 5,
                "addressdetails": 1,
                "countrycodes": "bg"
            }
            # Increase timeout for Cyrillic queries which may take longer
            timeout_val = 25 if has_cyrillic else 20
            
            try:
                response = requests.get(url, params=params, headers=headers, timeout=timeout_val)
                if response.status_code == 200:
                    data = response.json()
                    if data and len(data) > 0:
                        # Return the first result (street, not specific house)
                        result = data[0]
                        return {
                            "lat": float(result["lat"]),
                            "lng": float(result["lon"]),
                            "display_name": result.get("display_name", req.query) + " (approximate)"
                        }
            except requests.exceptions.Timeout:
                last_error = "Request timeout"
                print(f"Timeout for fallback query")
                pass  # Skip fallback if timeout
    except:
        pass
    
    # Final error message with helpful suggestions
    error_msg = f"Location '{req.query}' not found."
    if last_error:
        error_msg += f" Last error: {last_error}"
    
    if last_error and "503" in str(last_error):
        error_msg += " Nominatim service is temporarily unavailable. "
        error_msg += "You can enter coordinates directly (e.g., '42.6977, 23.3219') or try again in a few moments."
    else:
        error_msg += " Try entering coordinates directly (e.g., '42.6977, 23.3219') or check the address spelling."
    
    return {"error": error_msg}

# ---------- ELEVATION ----------
def get_elevations(coords):
    """Get elevation data for coordinates"""
    if len(coords) == 0:
        return []
    
    if not GOOGLE_KEY:
        print("Warning: Google Elevation API key not set")
        return []
    
    # Google Elevation API has a limit of 512 samples per request
    # Sample points intelligently
    max_samples = 200
    step = 1
    sampled_coords = coords
    
    if len(coords) > max_samples:
        step = max(1, len(coords) // max_samples)
        sampled_coords = coords[::step]
        # Always include first and last
        if len(sampled_coords) == 0 or sampled_coords[-1] != coords[-1]:
            sampled_coords.append(coords[-1])
    
    if len(sampled_coords) == 0:
        return []
    
    # Build path string: lat,lng|lat,lng|...
    path = "|".join([f"{lat},{lng}" for lat, lng in sampled_coords])
    
    url = "https://maps.googleapis.com/maps/api/elevation/json"
    try:
        response = requests.get(url, params={
            "path": path,
            "samples": len(sampled_coords),
            "key": GOOGLE_KEY
        }, timeout=15)
        
        if response.status_code != 200:
            print(f"Elevation API returned status {response.status_code}: {response.text[:200]}")
            return []
        
        try:
            r = response.json()
        except ValueError as e:
            print(f"Elevation API JSON parse error: {e}, Response: {response.text[:200]}")
            return []
        
        if "error_message" in r:
            error_msg = r['error_message']
            print(f"Elevation API error: {error_msg}")
            # If it's an IP restriction error, provide helpful message
            if "not authorized" in error_msg.lower() or "IP" in error_msg:
                print("NOTE: Your API key has IP restrictions. Add your server's IP address")
                print("to the allowed IPs list in Google Cloud Console, or remove IP restrictions.")
            return []
        
        if "results" not in r or len(r["results"]) == 0:
            print("No elevation results returned")
            return []
        
        elevs = [p["elevation"] for p in r["results"]]
        
        if len(elevs) == 0:
            return []
        
        # Interpolate if we sampled
        if len(coords) != len(elevs):
            full_elevs = []
            step_size = max(1, len(elevs) - 1) / max(1, len(coords) - 1) if len(coords) > 1 else 0
            for i, coord in enumerate(coords):
                if step_size > 0:
                    idx = min(int(i * step_size), len(elevs) - 1)
                else:
                    idx = 0
                full_elevs.append(elevs[idx])
            return full_elevs
        
        return elevs
    except Exception as e:
        print(f"Elevation API error: {e}")
        import traceback
        traceback.print_exc()
        return []

def elevation_gain(coords):
    """Calculate total elevation gain from coordinates"""
    if not coords or len(coords) < 2:
        return 0
    
    elevs = get_elevations(coords)
    if len(elevs) < 2:
        print(f"Warning: Only got {len(elevs)} elevation points for {len(coords)} coordinates")
        return 0
    
    if len(elevs) != len(coords):
        print(f"Warning: Elevation count ({len(elevs)}) doesn't match coordinate count ({len(coords)})")
    
    gain = 0
    for i in range(1, len(elevs)):
        diff = elevs[i] - elevs[i-1]
        if diff > 0:
            gain += diff
    
    return round(gain, 1)

# ---------- ROUTE GENERATION ----------
def get_route_distance(G, route):
    """Calculate total distance of a route by summing edge lengths"""
    total_distance = 0.0
    for i in range(len(route) - 1):
        u = route[i]
        v = route[i + 1]
        # Get edge data (handle multiple edges between nodes)
        edge_data = G.get_edge_data(u, v)
        if edge_data:
            # Get the first edge's length (or minimum if multiple edges)
            lengths = [data.get('length', 0) for data in edge_data.values()]
            if lengths:
                total_distance += min(lengths)  # Use shortest edge if multiple exist
    return total_distance

def calculate_edge_elevation_score(G, u, v, k, prefer):
    """Calculate elevation preference score for an edge"""
    edge_data = G[u][v][k]
    highway = edge_data.get("highway", "")
    surface = edge_data.get("surface", "")
    
    # Check if edge goes through green areas (parks, forests)
    # This is simplified - in production you'd query OSM for landuse tags
    score = 0
    
    if prefer == "green":
        if highway in ["footway", "path", "track", "bridleway"]:
            score = 0.5  # Prefer paths
        elif highway in ["residential", "living_street"]:
            score = 0.3
        else:
            score = 1.0
    elif prefer == "trail":
        if surface in ["dirt", "gravel", "ground", "grass", "unpaved"]:
            score = 0.3
        elif highway in ["path", "track", "footway"]:
            score = 0.5
        else:
            score = 1.0
    elif prefer == "road":
        if highway in ["primary", "secondary", "tertiary", "residential"]:
            score = 0.3
        elif surface in ["asphalt", "paved", "concrete"]:
            score = 0.4
        else:
            score = 1.0
    
    return score

def find_route_with_targets(G, start_node, end_node, target_distance_km, target_elevation_m, prefer, max_iterations=20):
    """Find a route that matches distance and elevation targets"""
    best_route = None
    best_score = float('inf')
    target_distance_m = target_distance_km * 1000
    
    # Get all nodes in the connected component containing start_node
    try:
        if G.is_directed():
            reachable_nodes = list(nx.descendants(G, start_node))
        else:
            # For undirected graphs, get all nodes in the same connected component
            component = nx.node_connected_component(G, start_node)
            reachable_nodes = list(component)
        
        # Filter to nodes that can reach end_node
        if end_node != start_node:
            reachable_nodes = [n for n in reachable_nodes if nx.has_path(G, n, end_node)]
        
        if not reachable_nodes:
            reachable_nodes = [end_node]
    except:
        # Fallback: just use end_node
        reachable_nodes = [end_node] if end_node != start_node else []
    
    # Try multiple random intermediate points
    for iteration in range(max_iterations):
        try:
            # For loop routes, find intermediate points
            if end_node == start_node:
                # Pick a random intermediate node
                if reachable_nodes:
                    intermediate = random.choice(reachable_nodes[:min(50, len(reachable_nodes))])
                    try:
                        route1 = nx.shortest_path(G, start_node, intermediate, weight="weight")
                        route2 = nx.shortest_path(G, intermediate, start_node, weight="weight")
                        route = route1[:-1] + route2  # Remove duplicate intermediate node
                    except nx.NetworkXNoPath:
                        # Try direct path if intermediate fails
                        if iteration == 0:
                            # For first iteration, try to find a simple loop
                            try:
                                # Find neighbors and create a small loop
                                neighbors = list(G.neighbors(start_node))
                                if neighbors:
                                    neighbor = neighbors[0]
                                    route1 = nx.shortest_path(G, start_node, neighbor, weight="weight")
                                    route2 = nx.shortest_path(G, neighbor, start_node, weight="weight")
                                    route = route1[:-1] + route2
                                else:
                                    route = [start_node]
                            except:
                                route = [start_node]
                        else:
                            continue
                else:
                    route = [start_node]
            else:
                # Try with optional intermediate point for distance matching
                if iteration > 0 and len(reachable_nodes) > 1:
                    intermediate = random.choice(reachable_nodes[:min(30, len(reachable_nodes))])
                    try:
                        route1 = nx.shortest_path(G, start_node, intermediate, weight="weight")
                        route2 = nx.shortest_path(G, intermediate, end_node, weight="weight")
                        route = route1[:-1] + route2
                    except:
                        route = nx.shortest_path(G, start_node, end_node, weight="weight")
                else:
                    route = nx.shortest_path(G, start_node, end_node, weight="weight")
            
            # Calculate route distance
            route_distance = get_route_distance(G, route)
            
            # Get coordinates
            coords = [(G.nodes[n]["y"], G.nodes[n]["x"]) for n in route]
            
            # Calculate elevation gain
            route_elevation = elevation_gain(coords)
            
            # Score based on how close we are to targets
            distance_diff = abs(route_distance - target_distance_m) / target_distance_m
            if target_elevation_m:
                elevation_diff = abs(route_elevation - target_elevation_m) / max(target_elevation_m, 1)
                score = distance_diff * 0.6 + elevation_diff * 0.4
            else:
                score = distance_diff
            
            # Prefer routes closer to target
            if score < best_score:
                best_score = score
                best_route = route
                
                # If we're close enough, return early
                if distance_diff < 0.15 and (not target_elevation_m or elevation_diff < 0.3):
                    break
                    
        except Exception as e:
            continue
    
    return best_route if best_route else nx.shortest_path(G, start_node, end_node, weight="weight")

@app.post("/generate")
def generate_route(req: RouteRequest):
    """Generate a route based on parameters"""
    center = (req.start_lat, req.start_lng)
    
    # Determine search radius (expand if elevation target is high)
    # Use minimum 2km radius to ensure we get enough nodes
    base_radius = max(req.distance_km * 1000 * 1.5, 2000)
    search_radius_multiplier = 1.5
    if req.elevation_gain_target and req.elevation_gain_target > 200:
        search_radius_multiplier = 2.0
    
    search_radius = base_radius * search_radius_multiplier
    
    try:
        # Download graph from OSM
        # Try 'all' network type first for better coverage, fallback to 'walk'
        try:
            G = ox.graph_from_point(
                center,
                dist=search_radius,
                network_type="all"
            )
        except:
            G = ox.graph_from_point(
                center,
                dist=search_radius,
                network_type="walk"
            )
        
        # Simplify graph for performance (only if not already simplified)
        try:
            G = ox.simplify_graph(G)
        except ValueError:
            # Graph is already simplified, continue with original graph
            pass
    except Exception as e:
        return {"error": f"Failed to load map data: {str(e)}"}
    
    if len(G.nodes) == 0:
        return {"error": f"No routes found in this area. Tried radius of {search_radius/1000:.1f}km around coordinates ({req.start_lat}, {req.start_lng})"}
    
    # Apply preference weights to edges
    for u, v, k, d in G.edges(keys=True, data=True):
        base_weight = d.get("length", 1)
        preference_score = calculate_edge_elevation_score(G, u, v, k, req.prefer)
        d["weight"] = base_weight * preference_score
    
    # Find start and end nodes using manual search (most reliable)
    def find_nearest_node(G, target_lat, target_lng):
        """Find the nearest node to given coordinates"""
        min_dist = float('inf')
        nearest = None
        
        for node in G.nodes():
            node_data = G.nodes[node]
            # OSMnx stores coordinates as 'y' (lat) and 'x' (lng)
            node_lat = node_data.get('y')
            node_lng = node_data.get('x')
            
            if node_lat is None or node_lng is None:
                continue
            
            # Calculate distance in degrees (approximate)
            dist = ((node_lat - target_lat)**2 + (node_lng - target_lng)**2)**0.5
            if dist < min_dist:
                min_dist = dist
                nearest = node
        
        return nearest, min_dist
    
    # Find start node
    start_node, start_dist = find_nearest_node(G, req.start_lat, req.start_lng)
    
    if start_node is None:
        # Try OSMnx built-in method as fallback
        try:
            start_node = ox.nearest_nodes(G, req.start_lng, req.start_lat)
        except:
            return {"error": f"Start location not accessible. Graph has {len(G.nodes)} nodes. Location: ({req.start_lat}, {req.start_lng}). Please try a different location or increase the distance."}
    
    # Find end node
    if req.end_lat is not None and req.end_lng is not None:
        end_node, end_dist = find_nearest_node(G, req.end_lat, req.end_lng)
        if end_node is None:
            # Try OSMnx built-in method as fallback
            try:
                end_node = ox.nearest_nodes(G, req.end_lng, req.end_lat)
            except:
                end_node = start_node
    else:
        end_node = start_node  # Loop route
    
    # Generate route
    try:
        if req.elevation_gain_target:
            route = find_route_with_targets(
                G, start_node, end_node, 
                req.distance_km, req.elevation_gain_target, 
                req.prefer
            )
        else:
            # Simple shortest path with preferences
            route = nx.shortest_path(G, start_node, end_node, weight="weight")
    except nx.NetworkXNoPath:
        return {"error": "No path found between start and end points"}
    except Exception as e:
        return {"error": f"Route generation failed: {str(e)}"}
    
    # Get coordinates (OSMnx stores as y=lat, x=lng)
    coords = []
    for n in route:
        node_data = G.nodes[n]
        lat = node_data.get("y")
        lng = node_data.get("x")
        if lat is not None and lng is not None:
            # Ensure coordinates are floats and in correct format
            try:
                coords.append([float(lat), float(lng)])  # [lat, lng] format for frontend
            except (ValueError, TypeError):
                continue  # Skip invalid coordinates
    
    if len(coords) == 0:
        return {"error": "Route generated but no valid coordinates found"}
    
    # Calculate actual distance
    distance = get_route_distance(G, route) / 1000
    
    # Calculate elevation gain
    # Use all coordinates for better accuracy, but limit to reasonable number
    elevation_coords = coords
    if len(coords) > 300:
        # Sample every Nth point if route is very long
        step = len(coords) // 300
        elevation_coords = coords[::step]
        if len(elevation_coords) > 0 and elevation_coords[-1] != coords[-1]:
            elevation_coords.append(coords[-1])  # Always include last point
    
    elevation = elevation_gain(elevation_coords)
    
    return {
        "distance_km": round(distance, 2),
        "elevation_gain_m": elevation,
        "coordinates": coords,
        "success": True
    }
