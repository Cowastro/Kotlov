<script>
document.addEventListener('DOMContentLoaded', function () {
    window.dataLayer = window.dataLayer || [];

    function pushEvent(eventName, parameters) {
        if (!/^[a-z0-9_]+$/i.test(eventName)) return;

        window.dataLayer.push(Object.assign({
            event: eventName,
            page_path: window.location.pathname
        }, parameters || {}));
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
