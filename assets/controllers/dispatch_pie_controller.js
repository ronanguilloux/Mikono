/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { Chart, PieController, ArcElement, Tooltip } from 'chart.js';

Chart.register(PieController, ArcElement, Tooltip);

/*
 * The pies on the stay page (ChartJsPie), the second chart drawn in
 * JavaScript after the origins map (ADR 0048). The server computes every
 * label, value and colour, and the legend beside the canvas prints them; this
 * only draws, adding the hover tooltip.
 */
export default class extends Controller {
    static values = { labels: Array, values: Array, colors: Array, unit: String };

    connect() {
        const unit = this.unitValue;
        this.chart = new Chart(this.element, {
            type: 'pie',
            data: {
                labels: this.labelsValue,
                // A lone slice's border would draw a white seam at twelve o'clock.
                datasets: [{ data: this.valuesValue, backgroundColor: this.colorsValue, borderColor: '#ffffff', borderWidth: this.valuesValue.length > 1 ? 2 : 0 }],
            },
            options: {
                animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (context) => ` ${context.parsed} ${unit}` } },
                },
            },
        });
    }

    disconnect() {
        this.chart?.destroy();
        this.chart = null;
    }
}
