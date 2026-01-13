// script.js

// Example: smooth scroll for internal links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e){
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

// Geocoding function
async function geocodeAddress(query) {
    if (!query || query.trim() === '') {
        return null;
    }
    
    try {
        const response = await fetch('http://localhost:8000/geocode', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ query: query.trim() })
        });
        
        if (!response.ok) {
            throw new Error(`Geocoding request failed: ${response.status} ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        if (!data.lat || !data.lng) {
            throw new Error('Invalid geocoding response: missing coordinates');
        }
        
        console.log('Geocoding successful:', data);
        return data;
    } catch (error) {
        console.error('Geocoding error:', error);
        throw error;
    }
}

// Map initialization
let map = null;
let routeLayer = null;

function initMap(center = [42.6977, 23.3219]) {
    const mapDiv = document.getElementById('map');
    if (!mapDiv) {
        console.error('Map div not found');
        return;
    }
    
    // Ensure map div is visible and has proper dimensions
    mapDiv.style.display = 'block';
    mapDiv.style.height = '400px';
    mapDiv.style.width = '100%';
    
    // Remove existing map if present
    if (map) {
        map.remove();
        map = null;
    }
    
    // Initialize map
    map = L.map('map', {
        center: center,
        zoom: 13,
        zoomControl: true,
        preferCanvas: false // Use SVG for better rendering
    });
    
    // Force map to initialize properly after a short delay
    setTimeout(() => {
        if (map) {
            map.invalidateSize();
        }
    }, 100);
    
    // Use multiple tile sources for better reliability - fix grey tiles issue
    // Try CartoDB first as it's more reliable, then fallback to OSM
    const cartoLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap contributors © CARTO',
        maxZoom: 19,
        subdomains: 'abcd',
        crossOrigin: true,
        updateWhenZooming: false,
        updateWhenIdle: true,
        noWrap: false
    });
    
    // Fallback: OpenStreetMap
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
        retry: 3,
        timeout: 10000,
        crossOrigin: true,
        updateWhenZooming: false,
        updateWhenIdle: true,
        noWrap: false
    });
    
    // Add CartoDB layer first (more reliable)
    cartoLayer.addTo(map);
    
    // Handle tile errors - switch to OSM if CartoDB fails
    let tileErrorCount = 0;
    cartoLayer.on('tileerror', function(error, tile) {
        tileErrorCount++;
        console.warn('CartoDB tile loading error:', error);
        if (tileErrorCount > 5 && !map.hasLayer(osmLayer)) {
            console.log('Switching to OpenStreetMap tiles');
            map.removeLayer(cartoLayer);
            osmLayer.addTo(map);
        }
    });
    
    // Ensure tiles load properly
    cartoLayer.on('tileload', function() {
        tileErrorCount = 0; // Reset error count on successful load
    });
    
    if (routeLayer) {
        map.removeLayer(routeLayer);
        routeLayer = null;
    }
}

function displayRoute(route) {
    if (!route || !route.coordinates || route.coordinates.length === 0) {
        console.error('No coordinates in route:', route);
        alert('Route generated but no coordinates to display. Check console for details.');
        return;
    }
    
    console.log('Raw route coordinates:', route.coordinates.slice(0, 5), '...', route.coordinates.slice(-2));
    
    // Ensure coordinates are in [lat, lng] format for Leaflet
    // OSMnx returns coordinates as (lat, lng) tuples
    const coords = route.coordinates.map((c, idx) => {
        // Handle both [lat, lng] and [lng, lat] formats
        if (Array.isArray(c) && c.length >= 2) {
            // OSMnx returns [lat, lng], Leaflet expects [lat, lng]
            const lat = parseFloat(c[0]);
            const lng = parseFloat(c[1]);
            if (isNaN(lat) || isNaN(lng)) {
                console.warn(`Invalid coordinate at index ${idx}:`, c);
                return null;
            }
            return [lat, lng];
        }
        console.warn(`Invalid coordinate format at index ${idx}:`, c);
        return null;
    }).filter(c => {
        // Filter out invalid coordinates
        if (c === null) return false;
        const valid = c[0] >= -90 && c[0] <= 90 && c[1] >= -180 && c[1] <= 180;
        if (!valid) {
            console.warn('Coordinate out of bounds:', c);
        }
        return valid;
    });
    
    if (coords.length === 0) {
        console.error('No valid coordinates found after filtering');
        console.error('Original coordinates:', route.coordinates);
        alert('Route coordinates are invalid. Check console for details.');
        return;
    }
    
    console.log('Displaying route with', coords.length, 'valid points');
    console.log('First coord:', coords[0], 'Last coord:', coords[coords.length - 1]);
    
    // Initialize map if not already done
    if (!map) {
        console.log('Initializing map...');
        initMap(coords[0]);
    }
    
    // Wait for map to be ready
    if (!map || !map.getContainer()) {
        console.error('Map not initialized properly');
        setTimeout(() => displayRoute(route), 500); // Retry after a delay
        return;
    }
    
    // Clear existing layers
    if (routeLayer) {
        map.removeLayer(routeLayer);
        routeLayer = null;
    }
    
    // Remove all existing markers (but keep tile layers)
    map.eachLayer(function(layer) {
        if (layer instanceof L.Marker || layer instanceof L.Polyline) {
            map.removeLayer(layer);
        }
    });
    
    // Add route polyline with better visibility - make it more prominent
    try {
        // Ensure map is initialized
        if (!map || !map.getContainer()) {
            console.error('Map container not found');
            return;
        }
        
        // Create route polyline with very visible styling
        routeLayer = L.polyline(coords, {
            color: '#ea5f94',
            weight: 10,
            opacity: 1.0,
            lineCap: 'round',
            lineJoin: 'round',
            smoothFactor: 0,
            interactive: true
        });
        
        // Add shadow/outline for better visibility
        const shadowLayer = L.polyline(coords, {
            color: '#000000',
            weight: 14,
            opacity: 0.4,
            lineCap: 'round',
            lineJoin: 'round',
            smoothFactor: 0,
            interactive: false
        });
        
        // Add layers to map (shadow first, then route on top)
        shadowLayer.addTo(map);
        routeLayer.addTo(map);
        
        // Bring route to front
        routeLayer.bringToFront();
        
        console.log('Route polyline added to map successfully');
        console.log('Route bounds:', routeLayer.getBounds());
        
        // Force map to invalidate size and refresh
        setTimeout(() => {
            map.invalidateSize();
            if (routeLayer) {
                map.fitBounds(routeLayer.getBounds(), {
                    padding: [50, 50],
                    maxZoom: 17
                });
            }
        }, 200);
        
    } catch (e) {
        console.error('Error adding route to map:', e);
        console.error('Stack trace:', e.stack);
        alert('Error displaying route on map: ' + e.message);
    }
    
    // Add start marker
    const startMarker = L.marker(coords[0], {
        icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        })
    }).addTo(map).bindPopup('Start');
    
    // Add end marker if different from start
    const lastCoord = coords[coords.length - 1];
    if (coords.length > 1 && 
        (Math.abs(lastCoord[0] - coords[0][0]) > 0.0001 || 
         Math.abs(lastCoord[1] - coords[0][1]) > 0.0001)) {
        L.marker(lastCoord, {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            })
        }).addTo(map).bindPopup('End');
    }
    
    // Fit map to route bounds with padding - ensure route is visible
    try {
        if (coords.length > 0) {
            const bounds = L.latLngBounds(coords);
            // Add padding to ensure route is fully visible
            map.fitBounds(bounds, {
                padding: [50, 50],
                maxZoom: 17,
                animate: true,
                duration: 0.5
            });
            
            console.log('Map fitted to route bounds');
            
            // Double-check after a short delay
            setTimeout(() => {
                if (routeLayer && map.hasLayer(routeLayer)) {
                    const routeBounds = routeLayer.getBounds();
                    map.fitBounds(routeBounds, {
                        padding: [50, 50],
                        maxZoom: 17
                    });
                    map.invalidateSize(); // Force refresh
                }
            }, 300);
        }
    } catch (e) {
        console.error('Error fitting bounds:', e);
        // Fallback: center on start point with good zoom
        if (coords.length > 0) {
            map.setView(coords[0], 15);
            map.invalidateSize();
        }
    }
}

// Generate Route form interaction
const genForm = document.querySelector('.gen-form');
if (genForm) {
    genForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const generateBtn = document.getElementById('generateBtn');
        const routeResult = document.getElementById('route-result');
        const errorMessage = document.getElementById('error-message');
        const routeDistance = document.getElementById('route-distance');
        const routeElevation = document.getElementById('route-elevation');
        
        // Hide previous results
        routeResult.style.display = 'none';
        errorMessage.style.display = 'none';
        
        // Show loading state
        generateBtn.disabled = true;
        generateBtn.textContent = 'Generating...';
        
        try {
            // Geocode start location
            const startQuery = document.getElementById('start').value.trim();
            if (!startQuery) {
                throw new Error('Please enter a start location');
            }
            
            // Check if user entered coordinates directly (e.g., "42.6977, 23.3219")
            let startLocation = null;
            const coordMatch = startQuery.match(/^(-?\d+\.?\d*),\s*(-?\d+\.?\d*)$/);
            if (coordMatch) {
                startLocation = {
                    lat: parseFloat(coordMatch[1]),
                    lng: parseFloat(coordMatch[2]),
                    display_name: `${coordMatch[1]}, ${coordMatch[2]}`
                };
                console.log('Using coordinates directly:', startLocation);
            } else {
                startLocation = await geocodeAddress(startQuery);
                if (!startLocation) {
                    throw new Error('Could not find start location');
                }
                // Show geocoded start display name
                if (startLocation.display_name) {
                    document.getElementById('start_geocoded_result').textContent = 'Found: ' + startLocation.display_name;
                }
            }
            
            document.getElementById('start_lat').value = startLocation.lat;
            document.getElementById('start_lng').value = startLocation.lng;
            
            // Geocode end location if provided
            const endQuery = document.getElementById('end').value.trim();
            let endLocation = null;
            
            if (endQuery) {
                // Check if user entered coordinates directly
                const endCoordMatch = endQuery.match(/^(-?\d+\.?\d*),\s*(-?\d+\.?\d*)$/);
                if (endCoordMatch) {
                    endLocation = {
                        lat: parseFloat(endCoordMatch[1]),
                        lng: parseFloat(endCoordMatch[2]),
                        display_name: `${endCoordMatch[1]}, ${endCoordMatch[2]}`
                    };
                } else {
                endLocation = await geocodeAddress(endQuery);
                // Show geocoded end display name
                if (endLocation && endLocation.display_name) {
                    document.getElementById('end_geocoded_result').textContent = 'Found: ' + endLocation.display_name;
                }
            }
            if (endLocation) {
                document.getElementById('end_lat').value = endLocation.lat;
                document.getElementById('end_lng').value = endLocation.lng;
            }
            } else {
                document.getElementById('end_lat').value = '';
                document.getElementById('end_lng').value = '';
            }
            
            // Prepare payload
            const payload = {
                start_lat: startLocation.lat,
                start_lng: startLocation.lng,
                end_lat: endLocation ? endLocation.lat : null,
                end_lng: endLocation ? endLocation.lng : null,
                distance_km: parseFloat(document.getElementById('distance').value) || 10,
                elevation_gain_target: document.getElementById('elevation_gain').value ? 
                    parseFloat(document.getElementById('elevation_gain').value) : null,
                prefer: document.getElementById('prefer').value || 'green'
            };
            
            // Generate route
            const response = await fetch('http://localhost:8000/generate', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            
            const route = await response.json();
            
            if (route.error) {
                throw new Error(route.error);
            }
            
            // Check if route has coordinates
            if (!route.coordinates || route.coordinates.length === 0) {
                throw new Error('Route generated but no coordinates returned');
            }
            
            console.log('Route received:', {
                distance: route.distance_km,
                elevation: route.elevation_gain_m,
                coordCount: route.coordinates.length,
                firstCoord: route.coordinates[0],
                lastCoord: route.coordinates[route.coordinates.length - 1]
            });
            
            // Display results
            routeDistance.textContent = `Distance: ${route.distance_km} km`;
            routeElevation.textContent = `Elevation Gain: ${route.elevation_gain_m} m`;
            
            // Make sure map container is visible and route result is shown
            const mapDiv = document.getElementById('map');
            if (mapDiv) {
                mapDiv.style.display = 'block';
                mapDiv.style.height = '400px';
                mapDiv.style.width = '100%';
            }
            
            // Show route result first
            routeResult.style.display = 'block';
            
            // Display route on map - use setTimeout to ensure DOM is ready and map can initialize
            setTimeout(() => {
                console.log('Attempting to display route...');
                displayRoute(route);
            }, 300);

            // --- Save generated route to DB via AJAX ---
            try {
                const saveData = {
                    coordinates: route.coordinates,
                    title: `Route from (${payload.start_lat}, ${payload.start_lng})`,
                    activity_type: payload.prefer,
                    description: route.description || '',
                    start_lat: payload.start_lat,
                    start_lng: payload.start_lng,
                    distance_km: route.distance_km,
                    elevation_gain_m: route.elevation_gain_m
                };
                const saveResp = await fetch('api/routes/save.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(saveData)
                });
                const saveResult = await saveResp.json();
                if (saveResult.success && saveResult.route_id) {
                    window.currentRouteId = saveResult.route_id;
                    let idHidden = document.getElementById('currentRouteId');
                    if (!idHidden) {
                        idHidden = document.createElement('input');
                        idHidden.type = 'hidden';
                        idHidden.id = 'currentRouteId';
                        document.body.appendChild(idHidden);
                    }
                    idHidden.value = saveResult.route_id;
                } else {
                    console.warn('Route save failed:', saveResult.error);
                }
            } catch(err) {
                console.warn('Route save/DB error:', err);
            }
            routeResult.style.display = 'block';
            // Scroll to results
            routeResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
        } catch (error) {
            errorMessage.textContent = `Error: ${error.message}`;
            errorMessage.style.display = 'block';
            console.error('Route generation error:', error);
        } finally {
            generateBtn.disabled = false;
            generateBtn.textContent = 'Generate Route';
        }
    });
}

// --- Appended: UI JS for Trails/Favs/Share (for new features only) ---
document.addEventListener('DOMContentLoaded', function() {
    // Favourite Functionality
    const favBtn = document.getElementById('favouriteRouteBtn');
    const favIcon = document.getElementById('favouriteIcon');
    let isFavourited = false; // This will be determined by backend/user state later
    if (favBtn) {
        favBtn.addEventListener('click', function() {
            const routeId = window.currentRouteId || document.getElementById('currentRouteId')?.value;
            if (!routeId) return alert("Route ID missing.");
            fetch('api/routes/favourite.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'route_id=' + encodeURIComponent(routeId)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    isFavourited = data.action === 'added';
                    favIcon.textContent = isFavourited ? '★' : '☆';
                    favIcon.style.color = isFavourited ? '#ffc300' : '#ea5f94';
                    favBtn.classList.toggle('favourited', isFavourited);
                } else {
                    alert('Favourite error: ' + (data.error || 'Unknown error'));
                }
            }).catch(() => alert('Cannot contact server.'));
        });
    }
    // Share Functionality
    const shareBtn = document.getElementById('shareRouteBtn');
    const shareModal = document.getElementById('shareModal');
    const closeShareModal = document.getElementById('closeShareModal');
    const shareLink = document.getElementById('shareLink');
    const copyShareLink = document.getElementById('copyShareLink');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            const routeId = window.currentRouteId || document.getElementById('currentRouteId')?.value;
            if (!routeId) return alert("Route ID missing.");
            fetch('api/routes/share.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'route_id=' + encodeURIComponent(routeId)
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert('Share error: ' + (data.error || 'Unknown error'));
                    return;
                }
                shareLink.value = window.location.origin + '/trails.php?route_id=' + routeId;
                shareModal.style.display = 'flex';
            }).catch(() => alert('Cannot contact server.'));
        });
    }
    if (closeShareModal) {
        closeShareModal.addEventListener('click', function() {
            shareModal.style.display = 'none';
        });
    }
    if (copyShareLink) {
        copyShareLink.addEventListener('click', function() {
            shareLink.select();
            document.execCommand('copy');
            copyShareLink.textContent = 'Copied!';
            setTimeout(() => {
                copyShareLink.textContent = 'Copy';
            }, 1100);
        });
    }
    // Hide modal on outside click
    if (shareModal) {
        shareModal.addEventListener('click', function(e) {
            if (e.target === shareModal) shareModal.style.display = 'none';
        });
    }
    // Trails page: tab switching (if present)
    function renderRoutes(list, routes) {
    list.innerHTML = '';
    if (!routes.length) {
        list.innerHTML = '<div class="no-routes">No routes to show. Generate or start favouriting routes!</div>';
        return;
    }
    for (const route of routes) {
        const div = document.createElement('div');
        div.className = 'trails-route-card';
        div.tabIndex = 0;
        div.style.cursor = 'pointer';
        div.innerHTML =
            `<div><b>${route.title}</b> (${route.distance_km} km, ${route.elevation_gain_m} m, ${route.activity_type})<br>
                <span style='font-size:.96em; color:#cebad0;'>${route.description||''}</span>
            </div>
            <div style='font-size:.93em; color:#ae89c9;'>Created: ${route.created_at.split(' ')[0]}</div>`;
        div.onclick = function() {
            openRouteMapModal(route.id, route.title, route);
        };
        list.appendChild(div);
    }
}

// --- Map Modal for Viewing Public/Favourite Route ---
function openRouteMapModal(route_id, routeTitle, routeMeta) {
    let modal = document.getElementById('routeMapModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'routeMapModal';
        modal.style = 'position:fixed; top:0; left:0; width:100vw;height:100vh;background:rgba(24,13,39,0.98); z-index:2050; display:flex; align-items:center;justify-content:center;';
        modal.innerHTML = `<div style="background:#2f1723;color:#f8eef7;min-width:300px;width:95vw; max-width:510px; min-height:350px;border-radius:18px;box-shadow:0 12px 56px #ea5f9470;position:relative;padding:2rem 2.25rem;display:flex;flex-direction:column;gap:1.1em;align-items:center;">
            <button id="closeRouteMapModal" style="position:absolute; top:0.65em; right:1.1em; border:none; background:none;color:#ea5f94;font-size:2em;cursor:pointer;">&times;</button>
            <div style="font-size:1.33em;font-weight:bold;margin-bottom:.4em;">${routeTitle || 'Route Map'}</div>
            <div id="modalMapMeta"></div>
            <div id="routeMapContainer" style="width:100%;min-width:270px; height:320px; background:#242; border-radius:9px; box-shadow:0 2px 19px #ea5f9412;"></div>
        </div>`;
        document.body.appendChild(modal);
    } else {
        modal.style.display = 'flex';
    }
    document.getElementById('closeRouteMapModal').onclick = function(){ modal.style.display='none'; };
    // Render metadata if available
    document.getElementById('modalMapMeta').innerHTML = routeMeta ? `<span style='color:#ddc2e0;'>Created: ${routeMeta.created_at && routeMeta.created_at.split(' ')[0]}</span><br><span style='color:#bbdaae;'>${routeMeta.description||''}</span>` : '';
    // Fetch and show the map
    fetch('api/routes/get_route_points.php?route_id=' + route_id)
        .then(res=>res.json())
        .then(data => {
            if (!data.success || !data.coordinates.length) {
                document.getElementById('routeMapContainer').innerHTML = '<div style="color:#eb9aac;padding:2em;text-align:center;">No points found for this route.</div>';
                return;
            }
            setTimeout(() => showModalMap('routeMapContainer', data.coordinates), 120);
        });
}

function showModalMap(containerId, coords) {
    // Fully remove any old map container and add a new, unique one each time.
    let parent = document.getElementById(containerId)?.parentNode;
    if (!parent) return;
    let mapId = "modalMap_" + Math.floor(Math.random() * 1e8);
    parent.innerHTML = `<div id="${mapId}" style="background:#222246;min-width:280px;width:100%;height:320px;border-radius:9px;box-shadow:0 2px 19px #ea5f9412;"></div>`;
    setTimeout(() => {
        const modalMap = L.map(mapId, {zoomControl:true});
        setTimeout(() => { modalMap.invalidateSize(); }, 120);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors', maxZoom: 19
        }).addTo(modalMap);
        const latlngs = coords.map(x => [x[0], x[1]]);
        if (latlngs.length >= 2) {
            L.polyline(latlngs, {color:'#ea5f94',weight:7,opacity:1.0}).addTo(modalMap);
            L.marker(latlngs[0], {icon: L.icon({
                iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
                shadowUrl:'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png', iconSize:[25,41], iconAnchor:[12,41], shadowSize:[41,41]
            })}).addTo(modalMap).bindPopup('Start');
            let end = latlngs[latlngs.length-1];
            L.marker(end, {icon: L.icon({
                iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                shadowUrl:'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png', iconSize:[25,41], iconAnchor:[12,41], shadowSize:[41,41]
            })}).addTo(modalMap).bindPopup('End');
            modalMap.fitBounds(L.latLngBounds(latlngs), {padding:[40,40], maxZoom:17});
        } else if (latlngs.length === 1) {
            modalMap.setView(latlngs[0], 15);
            L.marker(latlngs[0]).addTo(modalMap).bindPopup('Point');
        } else {
            document.getElementById(mapId).innerHTML = '<div style="color:#eb9aac;padding:2em;text-align:center;">No points found for this route.</div>';
        }
    }, 120);
}


    
    async function loadPublicRoutes(filters = {}) {
        let url = 'api/routes/public.php';
        let search = [];
        if (filters) {
            for (let key in filters) {
                if (filters[key]) search.push(`${encodeURIComponent(key)}=${encodeURIComponent(filters[key])}`);
            }
        }
        if (search.length) url += '?' + search.join('&');
        const resp = await fetch(url);
        const data = await resp.json();
        const list = document.getElementById('trailsList');
        if (!data.success || !data.routes) {
            list.innerHTML = '<div class="no-routes">Failed to load public routes.</div>';
            return;
        }
        renderRoutes(list, data.routes);
    }

    // Favourites (already loaded elsewhere if needed)
    async function loadFavourites() {
        const resp = await fetch('api/routes/favourites.php');
        const data = await resp.json();
        const list = document.getElementById('trailsList');
        if (!data.success || !data.routes) {
            list.innerHTML = '<div class="no-routes">Failed to load favourites.</div>';
            return;
        }
        renderRoutes(list, data.routes);
    }

    // Tab switching logic
    document.querySelectorAll('.trails-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.trails-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const which = tab.getAttribute('data-tab');
            if (which === 'public') {
                loadPublicRoutes();
            } else {
                loadFavourites();
            }
        });
    });
    // Initial load based on tab
    if (document.querySelector('.trails-tab.active')) {
        const which = document.querySelector('.trails-tab.active').getAttribute('data-tab');
        if (which === 'public') { loadPublicRoutes(); }
        else { loadFavourites(); }
    }
    // Filters
    document.getElementById('applyFilters')?.addEventListener('click', function() {
        const minDistance = document.getElementById('minDistance').value;
        const maxDistance = document.getElementById('maxDistance').value;
        const minElevation = document.getElementById('minElevation').value;
        const maxElevation = document.getElementById('maxElevation').value;
        const pavement = document.getElementById('pavementType').value;
        loadPublicRoutes({
            distance_min: minDistance,
            distance_max: maxDistance,
            elev_min: minElevation,
            elev_max: maxElevation,
            pavement_type: pavement
        });
    });
});