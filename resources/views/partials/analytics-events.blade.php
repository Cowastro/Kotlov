<script>
document.addEventListener('DOMContentLoaded', function () {
    window.dataLayer = window.dataLayer || [];

    function pushEvent(eventName, parameters) {
        if (!/^[a-z0-9_]+$/i.test(eventName)) return;

        window.dataLayer.push(Object.assign({
            event: eventName,
            page_path: window.location.pathname
        }, parameters || {}));

        var metrikaGoals = [
            'order_success',
            'install_request_success',
            'installer_application_success',
            'supplier_application_success',
            'heat_pump_lead_success',
            'fireplace_lead_success',
            'product_engineering_calculation_success',
            'pellet_burner_lead_success',
            'pellet_burner_evo_lead_success',
            'pellet_burner_hotta_lead_success'
        ];

        if (typeof window.ym === 'function' && metrikaGoals.indexOf(eventName) !== -1) {
            window.ym(113419397, 'reachGoal', eventName);

            if (eventName !== 'order_success' &&
                eventName !== 'install_request_success' &&
                eventName !== 'installer_application_success' &&
                eventName !== 'supplier_application_success') {
                window.ym(113419397, 'reachGoal', 'install_request_success');
            }
        }
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a');
        if (!link) return;

        var href = link.getAttribute('href') || '';
        var trackedEvent = link.dataset.analyticsEvent;

        if (trackedEvent) {
            pushEvent(trackedEvent, {
                link_url: href,
                link_text: (link.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 120)
            });
        }

        if (href.indexOf('tel:') === 0 || href.indexOf('mailto:') === 0) {
            pushEvent('contact_click', {
                contact_method: href.indexOf('tel:') === 0 ? 'phone' : 'email',
                contact_context: link.dataset.analyticsContext || 'site'
            });
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-analytics-form]');
        if (!form || event.defaultPrevented) return;

        pushEvent('generate_lead', {
            lead_type: form.dataset.analyticsForm
        });
    });

    @if (session('analytics_event'))
        pushEvent(@json(session('analytics_event')), @json(session('analytics_parameters', [])));
    @endif
});
</script>
