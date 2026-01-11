# TrailForgeX Setup Guide

## Overview
TrailForgeX is a Strava-like web application that generates custom running routes based on user parameters like distance, elevation gain, start/end points, and route preferences (green areas, trails, or roads).

## Prerequisites
- Python 3.8+
- PHP 7.4+ (with XAMPP or similar)
- Google Elevation API key

## Setup Instructions

### 1. Install Python Dependencies
```bash
pip install -r requirements.txt
```

### 2. Configure Environment Variables
Create a `.env` file in the project root:
```
GOOGLE_ELEVATION_API_KEY=your_google_elevation_api_key_here
```

To get a Google Elevation API key:
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the Elevation API
4. Create credentials (API key)
5. Copy the key to your `.env` file

### 3. Start the Python Backend Server
```bash
uvicorn route_service:app --reload --port 8000
```

The API will be available at `http://localhost:8000`

### 4. Start PHP Server
If using XAMPP:
- Place the project in `htdocs/TrailForgeX`
- Start Apache from XAMPP Control Panel
- Access at `http://localhost/TrailForgeX/generate.php`

Or use PHP's built-in server:
```bash
php -S localhost:8080
```

## Usage

1. Open `generate.php` in your browser
2. Enter your start location (address or place name)
3. Optionally enter an end location (leave empty for a loop route)
4. Set your desired distance in kilometers
5. Optionally set an elevation gain target in meters
6. Choose your route preference:
   - **Green Areas & Parks**: Prefers paths through parks and green spaces
   - **Trails & Dirt Paths**: Prefers unpaved trails and dirt paths
   - **Roads & Pavement**: Prefers paved roads and sidewalks
7. Click "Generate Route"
8. View your route on the interactive map

## API Endpoints

### POST /generate
Generate a route based on parameters.

**Request Body:**
```json
{
  "start_lat": 42.6977,
  "start_lng": 23.3219,
  "end_lat": null,
  "end_lng": null,
  "distance_km": 10,
  "elevation_gain_target": 300,
  "prefer": "green"
}
```

**Response:**
```json
{
  "distance_km": 10.5,
  "elevation_gain_m": 285,
  "coordinates": [[lat, lng], ...],
  "success": true
}
```

### POST /geocode
Geocode an address to coordinates.

**Request Body:**
```json
{
  "query": "Sofia, Bulgaria"
}
```

**Response:**
```json
{
  "lat": 42.6977,
  "lng": 23.3219,
  "display_name": "Sofia, Bulgaria"
}
```

## Features

- **Geocoding**: Automatically converts addresses to coordinates using OpenStreetMap Nominatim
- **Route Generation**: Creates routes matching distance and elevation targets
- **Route Preferences**: Choose between green areas, trails, or roads
- **Interactive Maps**: View generated routes on Leaflet maps
- **Loop Routes**: Generate round-trip routes from a single starting point
- **Elevation Tracking**: Uses Google Elevation API for accurate elevation data

## Technologies Used

- **Backend**: Python, FastAPI, OSMnx, NetworkX
- **Frontend**: PHP, JavaScript, Leaflet.js
- **APIs**: OpenStreetMap (Nominatim), Google Elevation API
- **Mapping**: OpenStreetMap tiles via Leaflet

## Notes

- The route generation algorithm tries to match your distance and elevation targets, but exact matches may not always be possible
- For best results, use realistic elevation gain targets (typically 50-500m for most routes)
- Route generation may take 10-30 seconds depending on area complexity
- Make sure both servers (Python and PHP) are running simultaneously



