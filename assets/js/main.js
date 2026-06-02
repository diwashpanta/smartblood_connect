(() => {
  const autoDismiss = document.querySelectorAll(".alert");
  autoDismiss.forEach((alert) => {
    setTimeout(() => {
      if (window.bootstrap) {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
        bsAlert.close();
      }
    }, 5000);
  });

  const confirmForms = document.querySelectorAll("[data-confirm]");
  confirmForms.forEach((form) => {
    form.addEventListener("submit", (event) => {
      const message = form.getAttribute("data-confirm") || "Are you sure?";
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
})();

