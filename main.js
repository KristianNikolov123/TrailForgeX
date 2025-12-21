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