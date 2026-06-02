(function () {
  async function loadRequestMap(mapId, requestId) {
    const map = window.SmartBloodMaps?.initMap(mapId, {});
    if (!map || !requestId) return;
    const res = await fetch(
      `${window.SMARTBLOOD_CONFIG.baseUrl}/api/request_markers.php?request_id=${encodeURIComponent(
        requestId
      )}`
    );
    const data = await res.json();
    if (!data.ok) return;

    const points = [];
    if (data.request) {
      const r = data.request;
      points.push({ lat: r.latitude, lng: r.longitude });
      map.addMarker({
        lat: r.latitude,
        lng: r.longitude,
        popup: `<strong>${r.hospital_name}</strong><br>${r.blood_group} | ${r.urgency}`,
      });
    }

    (data.donors || []).forEach((d) => {
      points.push({ lat: d.latitude, lng: d.longitude });
      map.addMarker({
        lat: d.latitude,
        lng: d.longitude,
        popup: `<strong>${d.full_name}</strong><br>${d.blood_group} | ${d.distance_km} km | ${d.status}`,
      });
    });
    map.fitToPoints(points);
  }

  window.PatientMaps = { loadRequestMap };
})();

