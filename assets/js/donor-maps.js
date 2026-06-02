(function () {
  async function loadDonorDashboardMap(mapId) {
    const map = window.SmartBloodMaps?.initMap(mapId, {});
    if (!map) return;

    const res = await fetch(`${window.SMARTBLOOD_CONFIG.baseUrl}/api/donor_notifications.php`);
    const data = await res.json();
    if (!data.ok) return;

    const points = [];
    if (data.donor_location) {
      points.push({
        lat: data.donor_location.latitude,
        lng: data.donor_location.longitude,
      });
      map.addMarker({
        lat: data.donor_location.latitude,
        lng: data.donor_location.longitude,
        popup: "<strong>Your saved location</strong>",
      });
    }

    (data.notifications || []).forEach((n) => {
      if (!Number.isFinite(Number(n.latitude)) || !Number.isFinite(Number(n.longitude))) return;
      points.push({ lat: Number(n.latitude), lng: Number(n.longitude) });
      map.addMarker({
        lat: Number(n.latitude),
        lng: Number(n.longitude),
        popup: `<strong>${n.hospital_name}</strong><br>${n.blood_group} | ${n.urgency} | ${n.distance_km} km`,
      });
    });
    map.fitToPoints(points);
  }

  window.DonorMaps = { loadDonorDashboardMap };
})();

