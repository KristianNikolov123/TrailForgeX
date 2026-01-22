// script.js

// Example: smooth scroll for internal links
document.querySelectorAll('a[href^=\"#\"]:not([href=\"#\"])').forEach(anchor => {
    anchor.addEventListener('click', function(e){
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
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

// =============================================
// Generate Route (TOP 3 options, start OR area)
// =============================================
const genForm = document.querySelector('.gen-form');
if (genForm) {
  genForm.addEventListener('submit', async function(e) {
    e.preventDefault();

    const generateBtn   = document.getElementById('generateBtn');
    const routeResult   = document.getElementById('route-result');
    const errorMessage  = document.getElementById('error-message');
    const routeDistance = document.getElementById('route-distance');
    const routeElevation= document.getElementById('route-elevation');

    const choicesWrap   = document.getElementById('routeChoices'); // from your updated generate.php
    if (choicesWrap) choicesWrap.style.display = 'none';
    if (choicesWrap) choicesWrap.innerHTML = '';

    // Hide previous results
    if (routeResult) routeResult.style.display = 'none';
    if (errorMessage) errorMessage.style.display = 'none';

    generateBtn.disabled = true;
    generateBtn.textContent = 'Generating 3 routes...';

    try {
      const prefer = document.getElementById('prefer')?.value || 'green';
      const distanceKm = parseFloat(document.getElementById('distance')?.value) || 10;
      const elevTarget = document.getElementById('elevation_gain')?.value
        ? parseFloat(document.getElementById('elevation_gain')?.value)
        : null;

      // detect mode (start vs area)
      const isAreaMode = document.getElementById('mode_area')?.checked === true;

      let payload = {
        distance_km: distanceKm,
        elevation_gain_target: elevTarget,   // backend should enforce +-5%
        prefer: prefer,
        n_routes: 3                          // backend generates exactly 3
      };

      if (!isAreaMode) {
        // ---------- START MODE ----------
        const startQuery = document.getElementById('start')?.value.trim() || '';
        if (!startQuery) throw new Error('Please enter a start location');

        // Geocode start
        let startLocation = null;
        const coordMatch = startQuery.match(/^(-?\d+\.?\d*),\s*(-?\d+\.?\d*)$/);
        if (coordMatch) {
          startLocation = { lat: parseFloat(coordMatch[1]), lng: parseFloat(coordMatch[2]), display_name: startQuery };
        } else {
          startLocation = await geocodeAddress(startQuery);
          if (startLocation?.display_name) {
            const el = document.getElementById('start_geocoded_result');
            if (el) el.textContent = 'Found: ' + startLocation.display_name;
          }
        }
        if (!startLocation) throw new Error('Could not find start location');

        document.getElementById('start_lat').value = startLocation.lat;
        document.getElementById('start_lng').value = startLocation.lng;

        // Optional end
        const endQuery = document.getElementById('end')?.value.trim() || '';
        let endLocation = null;

        if (endQuery) {
          const endCoordMatch = endQuery.match(/^(-?\d+\.?\d*),\s*(-?\d+\.?\d*)$/);
          if (endCoordMatch) {
            endLocation = { lat: parseFloat(endCoordMatch[1]), lng: parseFloat(endCoordMatch[2]), display_name: endQuery };
          } else {
            endLocation = await geocodeAddress(endQuery);
            if (endLocation?.display_name) {
              const el = document.getElementById('end_geocoded_result');
              if (el) el.textContent = 'Found: ' + endLocation.display_name;
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

        payload.start_lat = startLocation.lat;
        payload.start_lng = startLocation.lng;
        payload.end_lat   = endLocation ? endLocation.lat : null; // null => loop
        payload.end_lng   = endLocation ? endLocation.lng : null;
        payload.mode = endLocation ? 'point_to_point' : 'loop_from_start';

      } else {
        // ---------- AREA MODE (no start/end) ----------
        const areaQuery = document.getElementById('area')?.value.trim() || '';
        if (!areaQuery) throw new Error('Please enter an area/city/landmark');

        let center = null;
        const coordMatch = areaQuery.match(/^(-?\d+\.?\d*),\s*(-?\d+\.?\d*)$/);
        if (coordMatch) {
          center = { lat: parseFloat(coordMatch[1]), lng: parseFloat(coordMatch[2]), display_name: areaQuery };
        } else {
          center = await geocodeAddress(areaQuery);
          if (center?.display_name) {
            const el = document.getElementById('area_geocoded_result');
            if (el) el.textContent = 'Found: ' + center.display_name;
          }
        }
        if (!center) throw new Error('Could not find that area');

        document.getElementById('center_lat').value = center.lat;
        document.getElementById('center_lng').value = center.lng;

        payload.center_lat = center.lat;
        payload.center_lng = center.lng;
        payload.mode = 'loop_in_area';
      }

      // ✅ ONE call that returns 3 routes
      // Your backend endpoint should be /generate3 (FastAPI) or whatever you choose.
      // Change URL here if needed.
      const resp = await fetch('http://localhost:8000/generate3', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      
      const data = await resp.json().catch(() => null);
      if (!resp.ok) {
        // FastAPI 422 returns { detail: [...] }
        const detail = data?.detail ? JSON.stringify(data.detail) : '';
        const msg = data?.error || detail || resp.statusText || 'Request failed';
        throw new Error(`API error (${resp.status}): ${msg}`);
      }

      if (!data?.success) {
        throw new Error(data?.error || 'Failed to generate routes.');
      }

      
      if (!Array.isArray(data.routes) || data.routes.length === 0) {
        throw new Error('No routes returned.');
      }
      
      // show best one immediately:
      const bestRoute = data.routes[data.best_index ?? 0];

      if (routeDistance) routeDistance.textContent = `Distance: ${Number(bestRoute.distance_km).toFixed(2)} km`;
      if (routeElevation) routeElevation.textContent = `Elevation Gain: ${Number(bestRoute.elevation_gain_m).toFixed(0)} m`;
      if (routeResult) routeResult.style.display = 'block';


      displayRoute(bestRoute);
      
      // store for later
      window.lastGeneratedRoutes = data.routes;
      window.bestGeneratedIndex = data.best_index ?? 0;
      
      // Render the 3 cards
      renderRouteChoices(data.routes);
      

    } catch (error) {
      if (errorMessage) {
        errorMessage.textContent = `Error: ${error.message}`;
        errorMessage.style.display = 'block';
      }
      console.error('Route generation error:', error);
    } finally {
      generateBtn.disabled = false;
      generateBtn.textContent = 'Generate (3 Options)';
    }
  });
}

// ===============================
// Trails tab state (GLOBAL)
// ===============================
let currentTrailsTab = 'favourites'; // preventDefault
// ===============================
// Filters (GLOBAL so both DOMContentLoaded blocks can access)
// ===============================
let filtersDebounceTimer = null;

function getFilters() {
  return {
    distance_min: document.getElementById('minDistance')?.value ?? '',
    distance_max: document.getElementById('maxDistance')?.value ?? '',
    elev_min: document.getElementById('minElevation')?.value ?? '',
    elev_max: document.getElementById('maxElevation')?.value ?? '',
    pavement_type: document.getElementById('pavementType')?.value ?? ''
  };
}

function areFiltersEmpty(filters) {
  return !String(filters.distance_min).trim()
      && !String(filters.distance_max).trim()
      && !String(filters.elev_min).trim()
      && !String(filters.elev_max).trim()
      && !String(filters.pavement_type).trim();
}

function refreshActiveTab(filters = null) {
  if (currentTrailsTab === 'public') {
    return filters ? loadPublicRoutes(filters) : loadPublicRoutes();
  }
  if (currentTrailsTab === 'todo') {
    return filters ? loadTodoRoutes(filters) : loadTodoRoutes();
  }
  return filters ? loadFavourites(filters) : loadFavourites();
}


function onFiltersChanged() {
  const filters = getFilters();

  // If all filters cleared → auto reload
  if (areFiltersEmpty(filters)) {
    clearTimeout(filtersDebounceTimer);
    refreshActiveTab(null);
    return;
  }

  clearTimeout(filtersDebounceTimer);
  filtersDebounceTimer = setTimeout(() => {
    // refreshActiveTab(filters); // enable if you want live filtering
  }, 300);
}


function setTrailsTab(tabName) {
  currentTrailsTab = tabName;

  document.querySelectorAll('.trails-tab').forEach(t => t.classList.remove('active'));
  document.querySelector(`.trails-tab[data-tab="${tabName}"]`)?.classList.add('active');
}

document.querySelectorAll('.trails-tab').forEach(tab => {
  tab.addEventListener('click', function () {
    const which = this.getAttribute('data-tab');

    // ✅ update state + active class
    setTrailsTab(which);

    // ✅ load correct list
    if (which === 'public') loadPublicRoutes();
    else if (which === 'todo') loadTodoRoutes();
    else loadFavourites();
  });
});


// =============================================
// UI: render 3 route cards + let user pick one
// (uses CSS classes instead of inline styles)
// =============================================
function renderRouteChoices(routes) {
  const choicesWrap = document.getElementById('routeChoices');
  const routeResult = document.getElementById('route-result');
  const routeDistance = document.getElementById('route-distance');
  const routeElevation = document.getElementById('route-elevation');

  if (!choicesWrap) return;

  choicesWrap.style.display = 'block';
  choicesWrap.innerHTML = '';

  const top3 = (routes || []).slice(0, 3);

  top3.forEach((r, idx) => {
    const dist =
      r?.distance_km != null && !Number.isNaN(Number(r.distance_km))
        ? Number(r.distance_km).toFixed(2)
        : '—';

    const elev =
      r?.elevation_gain_m != null && !Number.isNaN(Number(r.elevation_gain_m))
        ? Number(r.elevation_gain_m).toFixed(0)
        : '—';

    const title = (r?.title && String(r.title).trim()) ? r.title : `Option ${idx + 1}`;
    const summary = (r?.summary && String(r.summary).trim())
      ? r.summary
      : 'Preview or select to show it on the map.';

    const card = document.createElement('div');
    card.className = 'choice-card';

    card.innerHTML = `
      <div class="choice-title">${escapeHtml(title)}</div>
      <div class="choice-meta">${dist} km · ⬆ ${elev} m</div>
      <div class="choice-meta" style="margin-top:.45rem;opacity:.95;">
        ${escapeHtml(summary)}
      </div>

      <div class="choice-actions">
        <button type="button" class="btn-share js-preview">Preview</button>
        <button type="button" class="cta-button route-btn js-select" style="margin-top:0;">
          Select
        </button>
      </div>
    `;

    // Guard to avoid double save (card click + button click)
    let selecting = false;

    const previewBtn = card.querySelector('.js-preview');
    const selectBtn = card.querySelector('.js-select');

    // Preview: show on map but do NOT save
    previewBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      showRouteOnMap(r, { routeResult, routeDistance, routeElevation });
    });

    // Select: show + save
    selectBtn?.addEventListener('click', async (e) => {
      e.stopPropagation();
      if (selecting) return;
      selecting = true;
      selectBtn.disabled = true;
      selectBtn.textContent = 'Selecting...';

      try {
        await selectRouteAndSave(r, { routeResult, routeDistance, routeElevation });
      } finally {
        // If save fails we re-enable, if succeeds you might want to keep disabled—your choice
        selectBtn.disabled = false;
        selectBtn.textContent = 'Select';
        selecting = false;
      }
    });

    // Optional: clicking the whole card previews (not select)
    card.addEventListener('click', () => {
      showRouteOnMap(r, { routeResult, routeDistance, routeElevation });
    });

    choicesWrap.appendChild(card);
  });
}

/* ------- Helpers ------- */

function showRouteOnMap(r, { routeResult, routeDistance, routeElevation }) {
  // Text values (safe)
  const distTxt =
    r?.distance_km != null && !Number.isNaN(Number(r.distance_km))
      ? `${Number(r.distance_km).toFixed(2)} km`
      : '—';

  const elevTxt =
    r?.elevation_gain_m != null && !Number.isNaN(Number(r.elevation_gain_m))
      ? `${Number(r.elevation_gain_m).toFixed(0)} m`
      : '—';

  if (routeDistance) routeDistance.textContent = `Distance: ${distTxt}`;
  if (routeElevation) routeElevation.textContent = `Elevation Gain: ${elevTxt}`;

  if (routeResult) routeResult.style.display = 'block';

  const mapDiv = document.getElementById('map');
  if (mapDiv) {
    mapDiv.style.display = 'block';
    mapDiv.style.height = '400px';
    mapDiv.style.width = '100%';
  }

  // Your existing renderer
  setTimeout(() => displayRoute(r), 120);

  routeResult?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function selectRouteAndSave(r, { routeResult, routeDistance, routeElevation }) {
  // Always show chosen route
  showRouteOnMap(r, { routeResult, routeDistance, routeElevation });

  // Save ONLY selected route
  try {
    const saveData = {
      coordinates: r.coordinates,
      title: r.title || 'Generated Route',
      activity_type: document.getElementById('prefer')?.value || 'green',
      description: r.description || '',
      start_lat: (r.start_lat != null ? r.start_lat : null),
      start_lng: (r.start_lng != null ? r.start_lng : null),
      distance_km: r.distance_km,
      elevation_gain_m: r.elevation_gain_m
    };

    const saveResp = await fetch('api/routes/save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(saveData)
    });

    const raw = await saveResp.text();
    let saveResult;
    try { saveResult = JSON.parse(raw); }
    catch { throw new Error('save.php returned non-JSON: ' + raw.slice(0, 200)); }

    console.log('save.php result:', saveResult);

    if (!saveResp.ok || !saveResult.success || !saveResult.route_id) {
      throw new Error(saveResult.error || 'Save failed');
    }

    window.currentRouteId = saveResult.route_id;

    const favBtn = document.getElementById('favouriteRouteBtn');
    if (favBtn) favBtn.disabled = false;

    let idHidden = document.getElementById('currentRouteId');
    if (!idHidden) {
      idHidden = document.createElement('input');
      idHidden.type = 'hidden';
      idHidden.id = 'currentRouteId';
      document.body.appendChild(idHidden);
    }
    idHidden.value = saveResult.route_id;

  } catch (err) {
    console.warn('Save error:', err);
    alert('Route was not saved, cannot favourite. Error: ' + (err.message || 'unknown'));
  }
}

// Tiny helper to prevent injecting HTML through title/summary
function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function initMiniRouteMap(minimapId, routeId) {
    fetch('api/routes/get_route_points.php?route_id=' + encodeURIComponent(routeId), {
      credentials: 'same-origin'
    })
      .then(res => res.json())
      .then(data => {
        if (!data.success || !data.coordinates || !data.coordinates.length) return;
  
        const container = document.getElementById(minimapId);
        if (!container) return;

        if (container.dataset.mapInit === "1") return;
        container.dataset.mapInit = "1";
  
        const miniMap = L.map(minimapId, {
          zoomControl: false,
          attributionControl: false,
          dragging: false,
          scrollWheelZoom: false,
          doubleClickZoom: false,
          boxZoom: false,
          keyboard: false,
          tap: false,
          touchZoom: false
        });
  
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 17
        }).addTo(miniMap);
  
        const latlngs = data.coordinates.map(p => [p[0], p[1]]);
        const line = L.polyline(latlngs, { color: '#ea5f94', weight: 5, opacity: 0.95 }).addTo(miniMap);
  
        miniMap.fitBounds(line.getBounds(), { padding: [20, 20], maxZoom: 16 });
  
        setTimeout(() => miniMap.invalidateSize(true), 100);
      })
      .catch(err => console.warn('initMiniRouteMap error:', err));
  }
  

// --- Route Card Rendering: renderRoutes (Global Scope) ---
function renderRoutes(list, routes) {
    if (!list) {
      console.warn('[renderRoutes] list container is null/undefined; skipping render.');
      return;
    }

    list.innerHTML = '';

    if (!routes || !routes.length) {
      list.innerHTML = '<div class="no-routes">No routes to show.</div>';
      return;
    }

    for (const route of routes) {
      const div = document.createElement('div');
      div.className = 'trails-route-card';
      div.tabIndex = 0;
  
      function formatPointName(name, lat, lng, fallback) {
        if (name && String(name).trim()) return name;
        if (lat != null && lng != null) {
          return `${lat.toFixed(3)}, ${lng.toFixed(3)}`;
        }
        return fallback;
      }
      
      const startName = formatPointName(
        route.start_name,
        route.start_lat,
        route.start_lng,
        'Start'
      );
      
      const endName = formatPointName(
        route.end_name,
        route.end_lat,
        route.end_lng,
        route.mode === 'loop_from_start' ? 'Loop' : 'End'
      );
      
      const routeLabel = `${startName} → ${endName}`      
      const minimapId = `minimap_${route.id}`;
      const isFav = route.is_favourited == 1;
      const isSaved = Number(route.is_saved) === 1 || Number(route.is_todo) === 1;

      div.innerHTML = `
        <div class="trails-route-map-mini">
          <div id="${minimapId}" class="trails-route-map-mini-inner"></div>
        </div>
  
        <div class="trails-route-names-overlay">
          🌄 ${routeLabel}
        </div>
  
        <div class="trails-route-stats-overlay">
          <span>${Number(route.distance_km).toFixed(2)} km</span>
          <span>⬆ ${route.elevation_gain_m} m</span>
        </div>
  
        ${isSaved ? `
          <span class="trails-done-btn" data-route-id="${route.id}" tabindex="0" title="Mark as done">✅</span>
        ` : ''}

        <span class="trails-fav-star" data-route-id="${route.id}" tabindex="0" style="opacity:${isFav ? 1 : 0.35};">
          ${isFav ? '★' : '☆'}
        </span>
  
        <span class="trails-save-pin" data-route-id="${route.id}" tabindex="0"
            style="opacity:${isSaved ? 1 : 0.35};">
            ${isSaved ? '📌' : '📍'}
        </span>
      `;
      
      div.addEventListener('click', (e) => {
        if (e.target.closest('.trails-fav-star') || e.target.closest('.trails-save-pin') || e.target.closest('.trails-done-btn')) return;
        openRouteMapModal(route.id, routeLabel, route);
      });
      list.appendChild(div);
      setTimeout(() => initMiniRouteMap(minimapId, route.id), 50);
    }
  }
  
        
  
        
  // --- Map Modal for Viewing Public/Favourite Route ---
  function openRouteMapModal(route_id, routeTitle, routeMeta) {
      let modal = document.getElementById('routeMapModal');
    
      // Helper: close modal + cleanup esc listener
      const closeModal = () => {
        if (!modal) return;
        modal.style.display = 'none';
        if (modal._escHandler) {
          document.removeEventListener('keydown', modal._escHandler);
          modal._escHandler = null;
        }
      };
    
      if (!modal) {
        modal = document.createElement('div');
        modal.id = 'routeMapModal';
        modal.style = 'position:fixed; top:0; left:0; width:100vw;height:100vh;background:rgba(24,13,39,0.98); z-index:2050; display:flex; align-items:center;justify-content:center;';
    
        modal.innerHTML = `
          <div id="routeMapModalCard"
               style="background:#2f1723;color:#f8eef7;min-width:300px;width:95vw; max-width:510px; min-height:350px;border-radius:18px;box-shadow:0 12px 56px #ea5f9470;position:relative;padding:2rem 2.25rem;display:flex;flex-direction:column;gap:1.1em;align-items:center;">
            <button id="closeRouteMapModal"
                    aria-label="Close"
                    style="position:absolute; top:0.65em; right:1.1em; border:none; background:none;color:#ea5f94;font-size:2em;cursor:pointer;">&times;</button>
            <div style="font-size:1.33em;font-weight:bold;margin-bottom:.4em;">${routeTitle || 'Route Map'}</div>
            <div id="modalMapMeta"></div>
            <div id="routeMapContainer"
                 style="width:100%;min-width:270px; height:320px; background:#242; border-radius:9px; box-shadow:0 2px 19px #ea5f9412;"></div>
          </div>
        `;
        document.body.appendChild(modal);
    
        // ✅ Close when clicking outside the card (overlay click)
        modal.addEventListener('click', (e) => {
          if (e.target === modal) closeModal();
        });
    
        // ✅ Close button
        modal.querySelector('#closeRouteMapModal').addEventListener('click', closeModal);
      } else {
        modal.style.display = 'flex';
        // Update title each time (since innerHTML isn't rebuilt)
        const card = modal.querySelector('#routeMapModalCard');
        if (card) {
          const titleDiv = card.querySelector('div');
          if (titleDiv) titleDiv.textContent = routeTitle || 'Route Map';
        }
      }
    
      // Render metadata if available
      document.getElementById('modalMapMeta').innerHTML = routeMeta
        ? `<span style='color:#ddc2e0;'>Created: ${routeMeta.created_at && routeMeta.created_at.split(' ')[0]}</span><br><span style='color:#bbdaae;'>${routeMeta.description || ''}</span>`
        : '';
    
      // ✅ Close on ESC
      modal._escHandler = (ev) => {
        if (ev.key === 'Escape') closeModal();
      };
      document.addEventListener('keydown', modal._escHandler);
    
      // Fetch and show the map
      fetch('api/routes/get_route_points.php?route_id=' + route_id)
        .then(res => res.json())
        .then(data => {
          if (!data.success || !data.coordinates.length) {
            document.getElementById('routeMapContainer').innerHTML =
              '<div style="color:#eb9aac;padding:2em;text-align:center;">No points found for this route.</div>';
            return;
          }
          setTimeout(() => showModalMap('routeMapContainer', data.coordinates), 120);
        });
    }
    
  
    function showModalMap(containerId, coords) {
      const container = document.getElementById(containerId);
      if (!container) return;
    
      // Create an inner div ONLY inside the container
      const mapId = "modalMap_" + Math.floor(Math.random() * 1e8);
      container.innerHTML = `<div id="${mapId}" style="background:#222246;width:100%;height:320px;border-radius:9px;box-shadow:0 2px 19px #ea5f9412;"></div>`;
    
      setTimeout(() => {
        const modalMap = L.map(mapId, { zoomControl: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '© OpenStreetMap contributors',
          maxZoom: 19
        }).addTo(modalMap);
    
        const latlngs = coords.map(x => [x[0], x[1]]);
    
        if (latlngs.length >= 2) {
          const line = L.polyline(latlngs, { color: '#ea5f94', weight: 7, opacity: 1.0 }).addTo(modalMap);
    
          L.marker(latlngs[0], { icon: L.icon({
            iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
            shadowUrl:'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize:[25,41], iconAnchor:[12,41], shadowSize:[41,41]
          })}).addTo(modalMap).bindPopup('Start');
    
          L.marker(latlngs[latlngs.length - 1], { icon: L.icon({
            iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
            shadowUrl:'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize:[25,41], iconAnchor:[12,41], shadowSize:[41,41]
          })}).addTo(modalMap).bindPopup('End');
    
          modalMap.fitBounds(line.getBounds(), { padding: [40, 40], maxZoom: 17 });
        } else if (latlngs.length === 1) {
          modalMap.setView(latlngs[0], 15);
          L.marker(latlngs[0]).addTo(modalMap).bindPopup('Point');
        } else {
          document.getElementById(mapId).innerHTML =
            '<div style="color:#eb9aac;padding:2em;text-align:center;">No points found for this route.</div>';
        }
    
        // 🔑 Fix grey/blank tiles in modals
        setTimeout(() => modalMap.invalidateSize(true), 150);
      }, 100);
    }

    async function loadTodoRoutes(filters = {}) {
      let url = 'api/routes/todo.php';
      const qs = new URLSearchParams();
    
      for (const [k, v] of Object.entries(filters || {})) {
        if (v !== undefined && v !== null && String(v).trim() !== '') {
          qs.set(k, v);
        }
      }
    
      const q = qs.toString();
      if (q) url += '?' + q;
    
      console.log('[todo] fetching:', url);
    
      const resp = await fetch(url, { credentials: 'same-origin' });
      const raw = await resp.text();
    
      let data;
      try { data = JSON.parse(raw); }
      catch {
        console.error('[todo] non-json:', raw);
        throw new Error('todo.php did not return JSON');
      }
    
      const list = document.getElementById('trailsList');
      if (!data.success || !data.routes) {
        list.innerHTML = '<div class="no-routes">Failed to load To-Do routes.</div>';
        return;
      }
    
      renderRoutes(list, data.routes);
    }
    

    async function loadPublicRoutes(filters = {}) {
      let url = 'api/routes/public.php';
      const search = [];
    
      for (const key in filters) {
        const v = filters[key];
        if (v !== undefined && v !== null && String(v).trim() !== '') {
          search.push(`${encodeURIComponent(key)}=${encodeURIComponent(v)}`);
        }
      }
    
      if (search.length) url += '?' + search.join('&');
    
      const resp = await fetch(url, { credentials: 'same-origin' });
      const raw = await resp.text();
    
      let data;
      try { data = JSON.parse(raw); }
      catch (e) {
        console.error("public.php returned non-JSON:", raw);
        throw new Error("public.php did not return JSON.");
      }
    
      const list = document.getElementById('trailsList');
      if (!list) return;
    
      if (!data.success || !data.routes) {
        list.innerHTML = '<div class="no-routes">Failed to load public routes.</div>';
        return;
      }
    
      renderRoutes(list, data.routes);
    }    

async function loadFavourites(filters = {}) {
  let url = 'api/routes/favourites.php';
  const search = [];

  for (const key in filters) {
    const v = filters[key];
    if (v !== undefined && v !== null && String(v).trim() !== '') {
      search.push(`${encodeURIComponent(key)}=${encodeURIComponent(v)}`);
    }
  }
  if (search.length) url += '?' + search.join('&');

  const resp = await fetch(url);
  const raw = await resp.text();

  let data;
  try {
    data = JSON.parse(raw);
  } catch (e) {
    console.error("favourites.php returned non-JSON:", raw);
    throw new Error("favourites.php did not return JSON (check console for raw response).");
  }

  const list = document.getElementById('trailsList');
  if (!list) {
    console.warn("[loadFavourites] #trailsList not found in DOM. Skipping render.");
    return;
  }

  if (!data.success || !data.routes) {
    list.innerHTML = '<div class="no-routes">Failed to load favourites.</div>';
    return;
  }

  renderRoutes(list, data.routes);
}

function initFeaturedBadgesPicker() {
  const checks = Array.from(document.querySelectorAll('.featureBadge'));
  const countEl = document.getElementById('featuredCount');
  const msgEl = document.getElementById('featuredMsg');
  const saveBtn = document.getElementById('saveFeaturedBadges');

  // Not on achievements page (or no earned badges)
  if (!countEl || !saveBtn) return;

  document.addEventListener('click', (e) => {
    if (e.target.closest('.ach-feature-toggle')) {
      e.stopPropagation();
    }
  }, true);

  function selectedCount() {
    return checks.filter(c => c.checked).length;
  }

  function updateCount() {
    if (countEl) countEl.textContent = String(selectedCount());
  }

  checks.forEach(c => {
    c.addEventListener('change', () => {
      const selected = checks.filter(x => x.checked);
      if (selected.length > 3) {
        c.checked = false;
        alert('You can only feature 3 badges.');
      }
      updateCount();
    });
  });

  updateCount();

  saveBtn.addEventListener('click', async () => {
    const ids = checks
      .filter(c => c.checked)
      .map(c => Number(c.dataset.achId))
      .filter(Boolean)
      .slice(0, 3);

    saveBtn.disabled = true;

    try {
      const res = await fetch('api/achievements/set_featured.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ badge_ids: ids })
      });

      const data = await res.json().catch(() => null);

      if (!data || !data.success) {
        alert(data?.error || 'Failed to save featured badges.');
        return;
      }

      if (msgEl) {
        msgEl.style.display = 'block';
        msgEl.style.color = '#65e68c';
        msgEl.textContent = 'Featured badges saved!';
        setTimeout(() => { msgEl.style.display = 'none'; }, 2200);
      }
    } catch (e) {
      console.error(e);
      alert('Failed to save (see console).');
    } finally {
      saveBtn.disabled = false;
    }
  });
}

function showUnlockedAchievements(data) {
  if (Array.isArray(data?.achievements_unlocked) && data.achievements_unlocked.length) {
    data.achievements_unlocked.forEach(a => window.showAchievementToast(a));
    return;
  }
  if (data?.achievement_unlocked) window.showAchievementToast(data.achievement_unlocked);
}


// ✅ make toast callable from anywhere (global)
window.showAchievementToast = function(ach){
    if (!ach) return;
  
    let wrap = document.querySelector('.toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'toast-wrap';
      document.body.appendChild(wrap);
    }
  
    const escapeHtml = (str) =>
      String(str).replace(/[&<>"']/g, s => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
      }[s]));
  
    const toast = document.createElement('div');
    toast.className = 'ach-toast';
  
    const earnedText = ach.earned_at ? `Earned: ${ach.earned_at}` : 'Achievement unlocked!';
    const ptsText = (ach.points != null) ? `${ach.points} pts` : '';
  
    toast.innerHTML = `
      <div class="row">
        <div class="icon">${ach.icon || '🏅'}</div>
        <div style="flex:1;">
          <p class="title">Badge earned: ${escapeHtml(ach.title || 'Achievement')}</p>
          <p class="desc">${escapeHtml(ach.description || '')}</p>
        </div>
      </div>
      <div class="meta">
        <span>${escapeHtml(earnedText)}</span>
        <span>${escapeHtml(ptsText)}</span>
      </div>
      <div class="actions">
        <button class="btn-close" type="button">Close</button>
        <button class="btn-view" type="button">View</button>
      </div>
    `;
  
    toast.querySelector('.btn-close').onclick = () => toast.remove();
    toast.querySelector('.btn-view').onclick = () => { window.location.href = 'achievements.php'; };
  
    wrap.appendChild(toast);
  
    setTimeout(() => { if (toast.isConnected) toast.remove(); }, 5500);
  };

  
  window.toggleFavourite = async function(routeId, starEl){
    try {
      const res = await fetch('api/routes/favourite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'route_id=' + encodeURIComponent(routeId)
      });
  
      const data = await res.json();
  
      if (!data.success) {
        alert(data.error || 'Favourite failed');
        return data; // return anyway for debugging
      }
  
      const added = data.action === 'added';
  
      if (starEl) {
        starEl.textContent = added ? '★' : '☆';
        starEl.style.opacity = added ? '1' : '0.35';
      }
  
      // ✅ show toast(s) for any unlocked achievements
      showUnlockedAchievements(data);
  
      // ✅ Only refresh lists where it matters
      const activeTab = document.querySelector('.trails-tab.active')?.getAttribute('data-tab');
      if (activeTab === 'public' || activeTab === 'favourites') {
        refreshActiveTab();
      }
  
      return data; // ✅ important
    } catch (err) {
      console.error(err);
      alert('Favourite failed (see console).');
      return null;
    }
  };
  
  
  
// --- AJAX Login Handler (and Register Handler) ---
document.addEventListener('DOMContentLoaded', function() {
    // Debug for script load
    console.log('main.js running!');

    const initial = document.querySelector('.trails-tab.active')?.getAttribute('data-tab') || 'favourites';
    setTrailsTab(initial);
    refreshActiveTab(null);
    // --- Auth guard (redirect if not logged in) ---
    (function checkAuthGuard() {
        // Only run on pages that require auth (e.g. trails.php, generate.php)
        // We detect them by presence of a known element or body class
        const requiresAuth =
        document.body.classList.contains('requires-auth') ||
        document.getElementById('trailsList') ||
        document.querySelector('.gen-form');
    
        if (!requiresAuth) return;
    
        fetch('api/routes/check_auth.php', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(jwt => {
            if (!jwt || !jwt.logged_in) {
            window.location.href = 'index.php';
            }
        })
        .catch(() => {
            // If auth check fails, fail closed
            window.location.href = 'index.php';
        });
    })();

    // --- Toggle Area Mode fields (Generate in area) ---
    const modeStart  = document.getElementById('mode_start');
    const modeArea   = document.getElementById('mode_area');
    const startBlock = document.getElementById('startBlock');
    const areaBlock  = document.getElementById('areaBlock');

    function syncGenerateMode() {
        if (!modeStart || !modeArea) return;

        const isArea = modeArea.checked;

        if (startBlock) startBlock.style.display = isArea ? 'none' : 'block';
        if (areaBlock)  areaBlock.style.display  = isArea ? 'block' : 'none';

        // Start required only in start mode
        const startInput = document.getElementById('start');
        if (startInput) startInput.required = !isArea;

        const clearValue = (id) => {
            const el = document.getElementById(id);
            if (el) el.value = '';
          };
        if (isArea) {
            clearValue('start_lat');
            clearValue('start_lng');
            clearValue('end_lat');
            clearValue('end_lng');
        } else {
            clearValue('center_lat');
            clearValue('center_lng');
        }

    }

    // Attach listeners
    if (modeStart) modeStart.addEventListener('change', syncGenerateMode);
    if (modeArea)  modeArea.addEventListener('change', syncGenerateMode);

    // Run once on load
    syncGenerateMode();

  // --- LOGIN FORM HANDLER ---
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.onsubmit = async function(e) {
      e.preventDefault();
      const username = document.getElementById('loginUsername').value;
      const password = document.getElementById('loginPassword').value;
      const res = await fetch('api/routes/login.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
      });

      let data;
      try {
        data = await res.json();
      } catch (err) {
        data = { success: false, error: 'Server error: invalid response' };
      }
      if (data.success) {
        window.location.reload();
      } else {
        document.getElementById('loginError').innerText = data.error || 'Login failed.';
      }
    };
  }

  // --- REGISTER FORM HANDLER (if you want SPA-style registration too) ---
  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.onsubmit = async function(e) {
      e.preventDefault();
      const username = document.getElementById('registerUsername').value;
      const email = document.getElementById('registerEmail').value;
      const password = document.getElementById('registerPassword').value;
      const res = await fetch('api/routes/register.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `username=${encodeURIComponent(username)}&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
      });
      let data;
      try {
        data = await res.json();
      } catch (err) {
        data = { success: false, error: 'Server error: invalid response' };
      }
      if (data.success) {
        window.location.reload();
      } else {
        document.getElementById('registerError').innerText = data.error || 'Registration failed.';
      }
    };
  }

// --- Appended: UI JS for Trails/Favs/Share (for new features only) ---

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
                    showUnlockedAchievements(data);
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
            if (!routeId) return alert("Please select a route first (so it can be saved), then publish.");
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
                alert('Route shared successfully');
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

    // Filters
    document.getElementById('applyFilters')?.addEventListener('click', function() {
      const filters = {
        distance_min: document.getElementById('minDistance')?.value || '',
        distance_max: document.getElementById('maxDistance')?.value || '',
        elev_min: document.getElementById('minElevation')?.value || '',
        elev_max: document.getElementById('maxElevation')?.value || '',
        pavement_type: document.getElementById('pavementType')?.value || ''
      };
    
      refreshActiveTab(filters);
    });
    
    


        // Attach listeners
        ['minDistance','maxDistance','minElevation','maxElevation','pavementType'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', onFiltersChanged);
        el.addEventListener('change', onFiltersChanged);
        });
      
});
// ===============================
// ACHIEVEMENTS PAGE MODAL (main.js)
// ===============================
// ---------- functions ----------

function initAchievementsPage() {
    const modal = document.getElementById('achModal');
    if (!modal) return;
  
    const closeBtn = document.getElementById('achModalClose');
  
    function openAchModal(card) {
  
        const title = card.dataset.title || 'Achievement'; 
        const desc = card.dataset.desc || ''; 
        const icon = card.dataset.icon || '🏅'; 
        const earned = card.dataset.earned === '1'; 
        const earnedDate = card.dataset.earnedDate || ''; 
        const points = card.dataset.points || '0'; 
        const titleEl = document.getElementById('achModalTitle'); 
        const descEl = document.getElementById('achModalDesc'); 
        const iconEl = document.getElementById('achModalIcon'); 
        const subEl = document.getElementById('achModalSub'); 
        const statusEl= document.getElementById('achModalStatus'); 
        const ptsEl = document.getElementById('achModalPoints'); 
        if (titleEl) titleEl.textContent = title; 
        if (descEl) descEl.textContent = desc; 
        if (iconEl) iconEl.textContent = icon; 
        if (subEl) { 
            subEl.textContent = earned 
            ? (earnedDate ? `Earned on ${earnedDate}` : 'Earned')
            : 'Not earned yet'; 
        } 
        if (statusEl) statusEl.textContent = 'Status: ' + (earned ? 'Earned ✅' : 'Locked 🔒'); 
        if (ptsEl) ptsEl.textContent = 'Points: ' + points; 
        modal.style.display = 'flex'; 
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeAchModal() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    }
  
    document.querySelectorAll('.ach-card').forEach(card => {
        card.addEventListener('click', () => openAchModal(card));
        card.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openAchModal(card);
          }
        });
    });
  
    closeBtn?.addEventListener('click', closeAchModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeAchModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAchModal(); });
  }

  function attachTrailsDoneHandler() {
    const trailsList = document.getElementById('trailsList');
    if (!trailsList) return;
  
    if (trailsList._doneHandlerAttached) return;
    trailsList._doneHandlerAttached = true;
  
    trailsList.addEventListener('click', async (e) => {
      const btn = e.target.closest('.trails-done-btn');
      if (!btn) return;
  
      e.preventDefault();
      e.stopPropagation();
  
      const routeId = btn.getAttribute('data-route-id');
      if (!routeId) return;
  
      // optional: ask duration
      const duration = prompt('How many minutes did it take? (optional)', '30');
      const durationMin = Math.max(0, parseInt(duration || '0', 10) || 0);
  
      try {
        const res = await fetch('api/routes/mark_done.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'route_id=' + encodeURIComponent(routeId) + '&duration_min=' + encodeURIComponent(durationMin)
        });
  
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); }
        catch { console.error('mark_done.php non-JSON:', raw); alert('Server returned invalid response.'); return; }
  
        if (!data.success) {
          alert(data.error || 'Failed to mark done.');
          return;
        }
  
        // toast achievements (your helper)
        showUnlockedAchievements(data);
  
        // refresh the active tab (so it disappears from To-Do)
        refreshActiveTab();
  
      } catch (err) {
        console.error(err);
        alert('Failed to mark done (see console).');
      }
    }, true);
  }
  
  
  function attachTrailsSaveHandler() {
    const trailsList = document.getElementById('trailsList');
    if (!trailsList) {
      console.warn('[save-pin] trailsList not found');
      return;
    }
  
    if (trailsList._saveHandlerAttached) return;
    trailsList._saveHandlerAttached = true;
  
    console.log('[save-pin] handler attached');
  
    trailsList.addEventListener('click', async (e) => {
      const pin = e.target.closest('.trails-save-pin');
      if (!pin) return;
  
      console.log('[save-pin] clicked', pin.getAttribute('data-route-id'));
  
      e.preventDefault();
      e.stopPropagation();
  
      const routeId = pin.getAttribute('data-route-id');
      if (!routeId) return;
  
      const res = await fetch('api/routes/save_later.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'route_id=' + encodeURIComponent(routeId)
      });
  
      const raw = await res.text();
      console.log('[save-pin] response raw:', raw);
  
      let data;
      try { data = JSON.parse(raw); }
      catch { alert('save_later.php returned non-JSON'); return; }
  
      if (!data.success) { alert(data.error || 'Save failed'); return; }
  
      const added = data.action === 'added';
      pin.textContent = added ? '📌' : '📍';
      pin.style.opacity = added ? '1' : '0.35';
  
      showUnlockedAchievements(data);
    }, true);
  }
  
  
  function attachTrailsStarHandler() {
    const trailsList = document.getElementById('trailsList');
    if (!trailsList) return;
  
    // ✅ robust one-time guard (property on the element)
    if (trailsList._favHandlerAttached) return;
    trailsList._favHandlerAttached = true;
  
    trailsList.addEventListener('click', (e) => {
      const star = e.target.closest('.trails-fav-star');
      if (!star) return;
  
      e.preventDefault();
      e.stopPropagation();
  
      const routeId = star.getAttribute('data-route-id');
      if (!routeId) return;
  
      window.toggleFavourite(routeId, star);
    }, true);
  }
  
  
  
  // ---------- one DOMContentLoaded ----------
  document.addEventListener('DOMContentLoaded', () => {
    // attach filter listeners here (so the elements exist)
    ['minDistance','maxDistance','minElevation','maxElevation','pavementType'].forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('input', onFiltersChanged);
      el.addEventListener('change', onFiltersChanged);
    });

    attachTrailsDoneHandler();
    attachTrailsStarHandler();
    attachTrailsSaveHandler();
    initAchievementsPage();
    initFeaturedBadgesPicker();
  
    document.getElementById('nav-login-btn')?.addEventListener('click', function(e){
      e.preventDefault();
      document.getElementById('loginModal').style.display = 'flex';
    });
  });
  