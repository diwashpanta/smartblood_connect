(function () {
  async function loadAdminMaps(requestMapId, donorMapId, requestId = "") {
    const requestMap = window.SmartBloodMaps?.initMap(requestMapId, {});
    const donorMap = window.SmartBloodMaps?.initMap(donorMapId, {});
    if (!requestMap || !donorMap) return;

    const query = requestId ? `?request_id=${encodeURIComponent(requestId)}` : "";
    const res = await fetch(`${window.SMARTBLOOD_CONFIG.baseUrl}/api/admin_map_data.php${query}`);
    const data = await res.json();
    if (!data.ok) return;

    const requestPoints = [];
    (data.requests || []).forEach((r) => {
      requestPoints.push({ lat: Number(r.latitude), lng: Number(r.longitude) });
      requestMap.addMarker({
        lat: Number(r.latitude),
        lng: Number(r.longitude),
        popup: `<strong>${r.hospital_name}</strong><br>${r.blood_group} | ${r.urgency} | ${r.request_status}`,
      });
    });
    requestMap.fitToPoints(requestPoints);

    const donorPoints = [];
    (data.donors || []).forEach((d) => {
      donorPoints.push({ lat: Number(d.latitude), lng: Number(d.longitude) });
      donorMap.addMarker({
        lat: Number(d.latitude),
        lng: Number(d.longitude),
        popup: `<strong>${d.full_name}</strong><br>${d.blood_group} | ${d.availability_status} | ${d.city || ""}`,
      });
    });
    donorMap.fitToPoints(donorPoints);
  }

  window.AdminMaps = { loadAdminMaps };
})();

