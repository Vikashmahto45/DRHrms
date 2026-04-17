<?php
// /admin/live_tracking_map.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'super_admin']);

$cid = $_SESSION['company_id'];
$branch_ids = getAccessibleBranchIds($pdo, $cid);
$cids_in = implode(',', $branch_ids);

// Fetch Active Field Staff
$stf_stmt = $pdo->prepare("SELECT id, name FROM users WHERE company_id IN ($cids_in) AND role IN ('staff', 'sales_person') AND status = 'active' ORDER BY name ASC");
$stf_stmt->execute();
$staff_list = $stf_stmt->fetchAll();

$target_user_id = $_GET['staff_id'] ?? ($staff_list[0]['id'] ?? 0);
$target_date = $_GET['date'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Field Tracker - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <style>
        #map { height: 75vh; width: 100%; border-radius: 12px; border: 1px solid var(--glass-border); z-index: 1; }
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; align-items: flex-end; background: #fff; padding: 15px; border-radius: 12px; border: 1px solid var(--glass-border); }
        .stop-popup { font-family: 'Inter', sans-serif; text-align: center; }
        .stop-popup strong { color: #ef4444; font-size:1.1rem; }
        .tracking-dashboard { display: flex; gap: 20px; height: 75vh; }
        .map-section { flex: 7; height: 100%; position: relative; }
        .timeline-section { flex: 3; height: 100%; background: #fff; border-radius: 12px; border: 1px solid var(--glass-border); padding: 20px; overflow-y: auto; }
        #map { height: 100%; width: 100%; border-radius: 12px; z-index: 1; }
        .timeline-item { position: relative; padding-left: 25px; margin-bottom: 20px; border-left: 2px solid #e5e7eb; }
        .timeline-item::before { content: ''; position: absolute; left: -7px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: var(--primary-color); }
        .timeline-item.start::before { background: #10b981; }
        .timeline-item.stop::before { background: #ef4444; }
        .timeline-time { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px; display: block; }
        .timeline-content { font-size: 0.95rem; font-weight: 500; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <div class="page-header" style="margin-bottom:1rem;">
            <div>
                <h1>🗺️ Live Agent Field Tracker</h1>
                <p style="color:var(--text-muted)">Monitor continuous GPS paths and automatically calculated stationary stops.</p>
            </div>
        </div>

        <div class="filter-bar">
            <div class="form-group" style="margin:0;">
                <label>Select Agent</label>
                <select id="staffSelect" class="form-control" style="width: 250px;">
                    <?php foreach($staff_list as $stf): ?>
                        <option value="<?= $stf['id'] ?>" <?= $target_user_id == $stf['id'] ? 'selected' : '' ?>><?= htmlspecialchars($stf['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Date</label>
                <input type="date" id="dateSelect" class="form-control" value="<?= htmlspecialchars($target_date) ?>">
            </div>
            <button class="btn btn-primary" onclick="loadMapData()">Load Route Map</button>
            <button class="btn btn-outline" id="btnDownload" onclick="downloadCSV()" style="display:none; border-color:#10b981; color:#10b981;">⬇️ Download Report (CSV)</button>
            <div id="loadingIndicator" style="display:none; color:var(--primary-color); font-weight:600;">Loading Map Data...</div>
        </div>

        <div class="tracking-dashboard">
            <div class="map-section"><div id="map"></div></div>
            <div class="timeline-section">
                <h3 style="margin-top:0; border-bottom: 1px solid var(--glass-border); padding-bottom:10px; margin-bottom:20px;">Daily Activity Log</h3>
                <div id="timelineContainer" style="color:var(--text-muted); text-align:center; margin-top:50px;">Select an agent and click Load to view activity.</div>
            </div>
        </div>

    </main>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
    let map = L.map('map').setView([20.5937, 78.9629], 5); // Default India
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    let routeLayer = null;
    let markerLayer = L.layerGroup().addTo(map);

    let currentPoints = [];
    let currentStops = [];

    async function loadMapData() {
        const staffId = document.getElementById('staffSelect').value;
        const date = document.getElementById('dateSelect').value;
        const loader = document.getElementById('loadingIndicator');
        
        if(!staffId) return alert('No staff selected');
        
        loader.style.display = 'inline-block';
        document.getElementById('btnDownload').style.display = 'none';
        
        try {
            const res = await fetch('../api/crm/live_tracking_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'get_route', target_user_id: staffId, target_date: date })
            });
            const data = await res.json();
            
            if(data.status === 'success') {
                currentPoints = data.points;
                currentStops = data.stops;
                drawMap(data.points, data.stops);
                document.getElementById('btnDownload').style.display = 'inline-block';
            } else {
                alert(data.message || 'Error loading data');
            }
        } catch(e) {
            console.error(e);
            alert('Failed to connect to tracking API');
        } finally {
            loader.style.display = 'none';
        }
    }

    function drawMap(points, stops) {
        if(routeLayer) map.removeLayer(routeLayer);
        markerLayer.clearLayers();

        if(!points || points.length === 0) {
            alert('No tracking data found for this user on the selected date.');
            document.getElementById('timelineContainer').innerHTML = "No data available.";
            return;
        }

        const latLngs = points.map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]);

        // Draw Polyline Route
        routeLayer = L.polyline(latLngs, {color: '#3b82f6', weight: 4, opacity: 0.8}).addTo(map);
        map.fitBounds(routeLayer.getBounds(), {padding: [50, 50]});

        // Setup Timeline UI
        let timelineHtml = `
            <div class="timeline-item start">
                <span class="timeline-time">${new Date(points[0].timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                <div class="timeline-content">🟢 Route Started</div>
            </div>
        `;

        // Add Start Marker
        const startPoint = latLngs[0];
        const endPoint = latLngs[latLngs.length - 1];
        L.marker(startPoint, {title: "Start"}).bindPopup("<b>🏁 Route Started</b><br>" + points[0].timestamp).addTo(markerLayer);
        
        // Draw Stops & Populate Timeline
        stops.forEach(stop => {
            const stopIcon = L.divIcon({
                className: 'custom-stop-icon',
                html: `<div style="background-color:#ef4444; width:16px; height:16px; border-radius:50%; border:2px solid #fff; box-shadow:0 0 5px rgba(0,0,0,0.5);"></div>`,
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });

            const marker = L.marker([stop.latitude, stop.longitude], {icon: stopIcon}).addTo(markerLayer);
            
            let popupHtml = `
                <div class="stop-popup">
                    <strong>🛑 Stop Detected</strong><br>
                    Duration: <b>${stop.duration_minutes} Minutes</b><br>
                    <span style="font-size:0.8rem; color:#6b7280;">From: ${new Date(stop.start_time).toLocaleTimeString()}</span><br>
                    <span style="font-size:0.8rem; color:#6b7280;">To: ${new Date(stop.end_time).toLocaleTimeString()}</span>
                </div>
            `;
            marker.bindPopup(popupHtml);

            // Timeline
            timelineHtml += `
                <div class="timeline-item stop">
                    <span class="timeline-time">${new Date(stop.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} to ${new Date(stop.end_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    <div class="timeline-content">🛑 Stopped (${stop.duration_minutes} Mins)</div>
                </div>
            `;
        });
        
        // Add End Marker & Timeline End
        L.circleMarker(endPoint, {radius: 8, fillColor: "#10b981", color: "#fff", weight: 2, fillOpacity: 1})
            .bindPopup("<b>📍 Latest Location</b><br>" + points[points.length-1].timestamp)
            .addTo(markerLayer);

        timelineHtml += `
            <div class="timeline-item">
                <span class="timeline-time">${new Date(points[points.length-1].timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                <div class="timeline-content">📍 Latest Known Location</div>
            </div>
        `;
        
        document.getElementById('timelineContainer').innerHTML = timelineHtml;
    }

    function downloadCSV() {
        if (!currentPoints || currentPoints.length === 0) return;
        const select = document.getElementById('staffSelect');
        const agentName = select.options[select.selectedIndex].text;
        const date = document.getElementById('dateSelect').value;
        
        let csv = "Agent Name,Date,Event Type,Start Time,End Time,Duration (Mins),Latitude,Longitude\\n";
        
        // Start
        csv += `"${agentName}","${date}","Route Started","${currentPoints[0].timestamp}","NA","0","${currentPoints[0].latitude}","${currentPoints[0].longitude}"\\n`;
        
        // Stops
        currentStops.forEach(stop => {
            csv += `"${agentName}","${date}","Stationary Stop","${stop.start_time}","${stop.end_time}","${stop.duration_minutes}","${stop.latitude}","${stop.longitude}"\\n`;
        });
        
        // End
        let last = currentPoints[currentPoints.length-1];
        csv += `"${agentName}","${date}","Latest Location/End","${last.timestamp}","NA","0","${last.latitude}","${last.longitude}"\\n`;

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('href', url);
        a.setAttribute('download', `Tracking_Report_${agentName.replace(/\\s+/g,'_')}_${date}.csv`);
        a.click();
    }

    // Auto load on init
    window.onload = () => { if(document.getElementById('staffSelect').value) loadMapData(); };
</script>
</body>
</html>
