<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>แผนที่พิกัดบ้าน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

  <style>
    #map {
      height: 100vh;
      width: 100%;
    }
  </style>
</head>
<body>
  <div id="map"></div>

  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

  <script>
    const map = L.map('map').setView([13.75, 100.5], 7); // กึ่งกลางประเทศไทย

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    fetch('data_house.php')
      .then(response => response.json())
      .then(data => {
        data.forEach(row => {
          const lat = parseFloat(row.latitude);
          const lng = parseFloat(row.longitude);
          const addr = row.address || row.house_id;

          if (!isNaN(lat) && !isNaN(lng)) {
            L.marker([lat, lng])
              .addTo(map)
              .bindPopup(`<b>${addr}</b><br>Lat: ${lat}, Lng: ${lng}`);
          }
        });
      })
      .catch(err => {
        console.error("โหลดข้อมูลพิกัดไม่สำเร็จ", err);
      });
  </script>
</body>
</html>
