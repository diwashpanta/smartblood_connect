(function () {
  const cfg = window.SMARTBLOOD_CONFIG || {};
  const mapCfg = cfg.map || {};

  function toLatLng(lat, lng) {
    const latNum = Number(lat);
    const lngNum = Number(lng);
    if (!Number.isFinite(latNum) || !Number.isFinite(lngNum)) return null;
    return { lat: latNum, lng: lngNum };
  }

  function openStreetMapAdapter(container, options) {
    const map = L.map(container).setView(
      [options.center.lat, options.center.lng],
      options.zoom || mapCfg.defaultZoom || 12
    );
    L.tileLayer(mapCfg.osmTileUrl || "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "&copy; OpenStreetMap contributors",
    }).addTo(map);

    const markers = [];
    return {
      map,
      markers,
      setView(lat, lng, zoom) {
        map.setView([lat, lng], zoom || map.getZoom());
      },
      clearMarkers() {
        markers.forEach((m) => map.removeLayer(m));
        markers.length = 0;
      },
      addMarker(opts) {
        const marker = L.marker([opts.lat, opts.lng], {
          draggable: !!opts.draggable,
        }).addTo(map);
        if (opts.popup) marker.bindPopup(opts.popup);
        if (typeof opts.onDragEnd === "function") {
          marker.on("dragend", () => {
            const p = marker.getLatLng();
            opts.onDragEnd(p.lat, p.lng);
          });
        }
        markers.push(marker);
        return marker;
      },
      onClick(cb) {
        map.on("click", (e) => cb(e.latlng.lat, e.latlng.lng));
      },
      fitToPoints(points) {
        const valid = points.filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng));
        if (!valid.length) return;
        const bounds = L.latLngBounds(valid.map((p) => [p.lat, p.lng]));
        map.fitBounds(bounds.pad(0.18));
      },
      invalidateSize() {
        setTimeout(() => map.invalidateSize(), 120);
      },
    };
  }

  function googleMapAdapter(container, options) {
    const map = new google.maps.Map(container, {
      center: options.center,
      zoom: options.zoom || mapCfg.defaultZoom || 12,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: true,
    });
    const markers = [];
    return {
      map,
      markers,
      setView(lat, lng, zoom) {
        map.setCenter({ lat, lng });
        if (zoom) map.setZoom(zoom);
      },
      clearMarkers() {
        markers.forEach((m) => m.setMap(null));
        markers.length = 0;
      },
      addMarker(opts) {
        const marker = new google.maps.Marker({
          map,
          position: { lat: opts.lat, lng: opts.lng },
          draggable: !!opts.draggable,
        });
        if (opts.popup) {
          const info = new google.maps.InfoWindow({ content: opts.popup });
          marker.addListener("click", () => info.open({ map, anchor: marker }));
        }
        if (typeof opts.onDragEnd === "function") {
          marker.addListener("dragend", (e) => opts.onDragEnd(e.latLng.lat(), e.latLng.lng()));
        }
        markers.push(marker);
        return marker;
      },
      onClick(cb) {
        map.addListener("click", (e) => cb(e.latLng.lat(), e.latLng.lng()));
      },
      fitToPoints(points) {
        const bounds = new google.maps.LatLngBounds();
        let count = 0;
        points.forEach((p) => {
          if (Number.isFinite(p.lat) && Number.isFinite(p.lng)) {
            bounds.extend({ lat: p.lat, lng: p.lng });
            count += 1;
          }
        });
        if (count > 0) map.fitBounds(bounds);
      },
      invalidateSize() {},
    };
  }

  window.SmartBloodMaps = {
    config: mapCfg,
    toLatLng,
    openExternalMap(lat, lng, label = "Location") {
      const safeLat = encodeURIComponent(String(lat));
      const safeLng = encodeURIComponent(String(lng));
      const safeLabel = encodeURIComponent(label);
      return `https://www.openstreetmap.org/?mlat=${safeLat}&mlon=${safeLng}#map=15/${safeLat}/${safeLng}&layers=N`;
    },
    initMap(containerId, options = {}) {
      const el = document.getElementById(containerId);
      if (!el) return null;
      const center = {
        lat: Number(options.lat ?? mapCfg.defaultLat ?? 27.7172),
        lng: Number(options.lng ?? mapCfg.defaultLng ?? 85.324),
      };
      const zoom = Number(options.zoom ?? mapCfg.defaultZoom ?? 12);
      const provider = mapCfg.provider === "google" && window.google && window.google.maps ? "google" : "osm";
      const adapter =
        provider === "google"
          ? googleMapAdapter(el, { center, zoom })
          : openStreetMapAdapter(el, { center, zoom });
      adapter.provider = provider;
      return adapter;
    },
  };
})();

