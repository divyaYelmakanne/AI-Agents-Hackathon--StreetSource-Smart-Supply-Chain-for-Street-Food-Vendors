<?php
/**
 * Google Maps Configuration
 * Add your Google Maps API Key here
 */

// Google Maps API Configuration
return [
    'api_key' => 'AIzaSyCz07V966JMJPy1We6CyOH1ycDgmCDpsGI', // Your Google Maps API key
    'default_zoom' => 14,
    'default_center' => [
        'lat' => 20.5937, // India center
        'lng' => 78.9629
    ],
    'map_style' => [
        // Custom map styling (optional)
        [
            'featureType' => 'poi',
            'elementType' => 'labels',
            'stylers' => [['visibility' => 'off']]
        ]
    ]
];

/**
 * How to get Google Maps API Key:
 * 
 * 1. Go to Google Cloud Console: https://console.cloud.google.com/
 * 2. Create a new project or select existing one
 * 3. Enable the following APIs:
 *    - Maps JavaScript API
 *    - Geolocation API
 *    - Places API (optional, for location search)
 * 4. Go to Credentials → Create Credentials → API Key
 * 5. Restrict the API key to your domain for security
 * 6. Copy the API key and paste it above
 * 
 * Security Notes:
 * - Never expose your API key in public repositories
 * - Always restrict API key to specific domains
 * - Monitor API usage in Google Cloud Console
 * - Set billing alerts to avoid unexpected charges
 */
?>
