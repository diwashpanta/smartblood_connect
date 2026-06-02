(function () {
  function readCoordinate(input) {
    if (!input) return NaN;
    const raw = String(input.value ?? "").trim();
    if (raw === "") return NaN;
    const value = Number(raw);
    return Number.isFinite(value) ? value : NaN;
  }

  function setInputValue(input, value) {
    if (!input) return;
    input.value = String(value ?? "");
  }

  function initLocationPicker(options) {
    const map = window.SmartBloodMaps?.initMap(options.mapId, {
      lat: options.defaultLat,
      lng: options.defaultLng,
      zoom: options.zoom || 13,
    });
    if (!map) return null;

    const latInput = document.querySelector(options.latSelector);
    const lngInput = document.querySelector(options.lngSelector);
    const preview = options.previewSelector ? document.querySelector(options.previewSelector) : null;
    const searchInput = options.searchInputSelector ? document.querySelector(options.searchInputSelector) : null;
    const searchBtn = options.searchButtonSelector ? document.querySelector(options.searchButtonSelector) : null;
    const gpsBtn = options.gpsButtonSelector ? document.querySelector(options.gpsButtonSelector) : null;
    const addressInput = options.addressSelector ? document.querySelector(options.addressSelector) : null;
    const cityInput = options.citySelector ? document.querySelector(options.citySelector) : null;
    const baseUrl = window.SMARTBLOOD_CONFIG?.baseUrl || "";
    let reverseLookupCounter = 0;

    let marker = null;
    function updatePreview(text, latNum, lngNum) {
      if (!preview) return;
      preview.textContent = (text ? `${text} | ` : "") + `${latNum.toFixed(5)}, ${lngNum.toFixed(5)}`;
    }

    async function reverseGeocode(latNum, lngNum, sourceText) {
      if (!baseUrl) return;
      const callId = ++reverseLookupCounter;
      try {
        const res = await fetch(
          `${baseUrl}/api/geocode.php?lat=${encodeURIComponent(String(latNum))}&lng=${encodeURIComponent(
            String(lngNum)
          )}`
        );
        const data = await res.json();
        if (callId !== reverseLookupCounter || !data?.ok || !data.result) return;

        const result = data.result;
        if (addressInput && result.address) setInputValue(addressInput, result.address);
        if (cityInput && result.city) setInputValue(cityInput, result.city);

        if (preview) {
          const label = result.display_name || result.address || sourceText || "Selected location";
          preview.textContent = `${label} | ${latNum.toFixed(5)}, ${lngNum.toFixed(5)}`;
        }
      } catch (_) {
        updatePreview(sourceText, latNum, lngNum);
      }
    }

    function update(lat, lng, text, extra = {}) {
      const latNum = Number(lat);
      const lngNum = Number(lng);
      if (!Number.isFinite(latNum) || !Number.isFinite(lngNum)) return;
      if (latInput) latInput.value = latNum.toFixed(7);
      if (lngInput) lngInput.value = lngNum.toFixed(7);
      if (!marker) {
        marker = map.addMarker({
          lat: latNum,
          lng: lngNum,
          draggable: true,
          onDragEnd: (nLat, nLng) => update(nLat, nLng, "Marker updated", { resolveAddress: true }),
        });
      } else if (marker.setLatLng) {
        marker.setLatLng([latNum, lngNum]);
      } else if (marker.setPosition) {
        marker.setPosition({ lat: latNum, lng: lngNum });
      }
      map.setView(latNum, lngNum, 15);

      if (extra.address && addressInput) {
        setInputValue(addressInput, extra.address);
      }
      if (extra.city && cityInput) {
        setInputValue(cityInput, extra.city);
      }

      if (extra.displayName && preview) {
        preview.textContent = `${extra.displayName} | ${latNum.toFixed(5)}, ${lngNum.toFixed(5)}`;
      } else {
        updatePreview(text, latNum, lngNum);
      }

      if (extra.resolveAddress) {
        reverseGeocode(latNum, lngNum, text || "Selected location");
      }
    }

    const existingLat = readCoordinate(latInput);
    const existingLng = readCoordinate(lngInput);
    if (
      Number.isFinite(existingLat) &&
      Number.isFinite(existingLng) &&
      !(Math.abs(existingLat) < 0.0001 && Math.abs(existingLng) < 0.0001)
    ) {
      update(existingLat, existingLng, "Saved location");
    }

    map.onClick((lat, lng) => update(lat, lng, "Selected from map", { resolveAddress: true }));

    if (gpsBtn) {
      gpsBtn.addEventListener("click", () => {
        if (!navigator.geolocation) {
          if (preview) preview.textContent = "Geolocation is not available in this browser.";
          return;
        }
        navigator.geolocation.getCurrentPosition(
          (pos) =>
            update(pos.coords.latitude, pos.coords.longitude, "Current location", {
              resolveAddress: true,
            }),
          () => {
            if (preview) preview.textContent = "Location permission denied. Use map click/search.";
          },
          { enableHighAccuracy: true, timeout: 15000 }
        );
      });
    }

    if (searchBtn && searchInput) {
      const runSearch = async () => {
        const q = searchInput.value.trim();
        if (!q) return;
        try {
          const res = await fetch(
            `${window.SMARTBLOOD_CONFIG.baseUrl}/api/geocode.php?q=${encodeURIComponent(q)}`
          );
          const data = await res.json();
          if (!data.ok || !data.results?.length) {
            if (preview) preview.textContent = "No location found. Try another search.";
            return;
          }
          const item = data.results[0];
          update(item.lat, item.lng, "Search result", {
            address: item.address || "",
            city: item.city || "",
            displayName: item.display_name || "",
            resolveAddress: !item.address && !item.city,
          });
        } catch (_) {
          if (preview) preview.textContent = "Search failed. You can still click on the map.";
        }
      };
      searchBtn.addEventListener("click", runSearch);
      searchInput.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          runSearch();
        }
      });
    }

    return { map, update };
  }

  window.SmartBloodLocationPicker = { initLocationPicker };
})();
