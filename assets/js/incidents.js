new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: window.categoryLabels,
        datasets: [{
            label: 'Incidents',
            data: window.categoryValues
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

new Chart(document.getElementById('urgencyChart'), {
    type: 'pie',
    data: {
        labels: window.urgencyLabels,
        datasets: [{
            data: window.urgencyValues
        }]
    },
    options: {
        responsive: true
    }
});