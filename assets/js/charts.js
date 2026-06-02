window.SmartBloodCharts = {
  createBarChart(canvasId, labels, data, label = "Dataset") {
    const target = document.getElementById(canvasId);
    if (!target || typeof Chart === "undefined") {
      return null;
    }

    return new Chart(target, {
      type: "bar",
      data: {
        labels,
        datasets: [
          {
            label,
            data,
            borderRadius: 8,
            backgroundColor: "rgba(190, 24, 93, 0.74)",
            borderColor: "rgba(190, 24, 93, 1)",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: "bottom" },
        },
      },
    });
  },

  createDoughnutChart(canvasId, labels, data) {
    const target = document.getElementById(canvasId);
    if (!target || typeof Chart === "undefined") {
      return null;
    }

    return new Chart(target, {
      type: "doughnut",
      data: {
        labels,
        datasets: [
          {
            data,
            backgroundColor: [
              "#be123c",
              "#1d4ed8",
              "#f59e0b",
              "#22c55e",
              "#64748b",
              "#9333ea",
            ],
            borderWidth: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: "bottom" },
        },
      },
    });
  },
};

