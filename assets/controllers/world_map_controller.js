/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import jsVectorMap from 'jsvectormap';
import 'jsvectormap/dist/jsvectormap.min.css';

/*
 * The world map on /reports/origins (pan, zoom, tooltip): the one chart drawn
 * in JavaScript (ADR 0048). The server computes each country's fill and
 * tooltip; this only draws them.
 */
export default class extends Controller {
    static values = { fills: Object, titles: Object, noVolunteers: String };

    async connect() {
        // The map file registers itself on the global, not through an export.
        window.jsVectorMap = jsVectorMap;
        await import('jsvectormap/dist/maps/world.js');
        if (!this.element.isConnected) {
            return;
        }

        const titles = this.titlesValue;
        this.map = new jsVectorMap({
            selector: this.element,
            map: 'world',
            zoomOnScroll: false,
            regionStyle: {
                initial: { fill: this.noVolunteersValue, stroke: '#ffffff', strokeWidth: 0.5, fillOpacity: 1 },
                hover: { fillOpacity: 0.8, cursor: 'pointer' },
            },
            onRegionTooltipShow(event, tooltip, code) {
                tooltip.text(titles[code] ?? `${tooltip.text()}: no volunteers`);
            },
        });

        for (const [code, fill] of Object.entries(this.fillsValue)) {
            this.map.regions[code]?.element.setStyle('fill', fill);
        }

        // The map measures its container once, and the container only gets
        // its height when the stylesheet (injected by the import above) lands.
        this.resizeObserver = new ResizeObserver(() => this.map?.updateSize());
        this.resizeObserver.observe(this.map.container);
    }

    disconnect() {
        this.resizeObserver?.disconnect();
        this.map?.destroy();
        this.map = null;
        // destroy() leaves the drawing behind; Turbo's cached copy would get a second one.
        this.element.replaceChildren();
    }
}
