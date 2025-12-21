# Google Elevation API Setup Guide

## Current Issue
Your Google Elevation API key has **IP address restrictions** enabled, but your server's IP address is not whitelisted.

## Error Message
```
This IP, site or mobile application is not authorized to use this API key.
Request received from IP address: 2a01:5a8:307:6c99:11ac:d3e2:295e:446b
```

## Solutions

### Option 1: Add Your Server IP to Allowed List (Recommended for Production)
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Navigate to **APIs & Services** > **Credentials**
3. Click on your API key ("Maps Platform API Key")
4. Under **Application restrictions**, find **IP addresses**
5. Click **Add an item**
6. Add your server's IP address: `2a01:5a8:307:6c99:11ac:d3e2:295e:446b`
   - Note: This is an IPv6 address. If you also have IPv4, add that too.
   - To find your server's IP: Run `curl ifconfig.me` or check your server's network settings
7. Click **Save**

### Option 2: Remove IP Restrictions (For Development Only)
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Navigate to **APIs & Services** > **Credentials**
3. Click on your API key
4. Under **Application restrictions**, select **None** (or "HTTP referrers" if you want web restrictions instead)
5. Click **Save**

⚠️ **Warning**: Removing IP restrictions makes your API key accessible from anywhere. Only do this for development/testing.

### Option 3: Use HTTP Referrer Restrictions Instead
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Navigate to **APIs & Services** > **Credentials**
3. Click on your API key
4. Under **Application restrictions**, select **HTTP referrers (web sites)**
5. Add your domain: `http://localhost/*` or `http://127.0.0.1/*`
6. Click **Save**

## Note
The route generation will still work **without** elevation data. You'll see "Elevation Gain: 0 m" until the API key is properly configured, but the route will still be generated and displayed on the map.

## Testing
After updating your API key restrictions:
1. Restart your Python server: `uvicorn route_service:app --reload --port 8000`
2. Generate a new route
3. Check the terminal - you should no longer see elevation API errors
4. Elevation gain should show a non-zero value (if there's elevation change in your route)

