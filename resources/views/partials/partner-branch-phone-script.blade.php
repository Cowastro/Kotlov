{{-- Reveals a sales point's phone on click and logs it (route partner-branches.phone). Shared by the product card and the footer. --}}
@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-wtb-phone]');
                if (!button) return;
                button.disabled = true;
                var token = document.querySelector('meta[name="csrf-token"]');
                fetch(button.dataset.wtbPhone, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
                    body: JSON.stringify({ product_id: button.dataset.productId || null })
                }).then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                  .then(function (data) {
                      var box = document.createElement('div');
                      box.className = button.dataset.wtbPhonesClass || 'wtb__phones';
                      (data.phones || []).forEach(function (phone) {
                          var link = document.createElement('a');
                          link.href = 'tel:' + phone.href;
                          link.textContent = phone.display;
                          box.appendChild(link);
                      });
                      button.closest('[data-wtb-slot]').replaceChildren(box);
                  })
                  .catch(function () { button.disabled = false; });
            });
        </script>
    @endpush
@endonce
