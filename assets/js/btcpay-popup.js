(function($) {
    $(document).ready(function() {
        $('.csc-btn-generate').off('click').on('click', function(e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.csc-generate-key-wrapper, .csc-field-row, .bif-generate-key-wrapper');
            var host = $wrapper.find('input[type="url"]').val() || $(this).siblings('input[type="url"]').val();
            if (!host || host.indexOf('http') === -1) {
                alert('Please enter a valid URL including https:// for your BTCPay Server.');
                return;
            }
            try { new URL(host); } catch(err) {
                alert('Please enter a valid URL including https:// for your BTCPay Server.');
                return;
            }
            if (typeof CoinsnapCoreAdmin === 'undefined' || !CoinsnapCoreAdmin.ajax_url) return;
            $.post(CoinsnapCoreAdmin.ajax_url, {
                action: CoinsnapCoreAdmin.btcpay_action || 'cbv_btcpay_apiurl_handler',
                host: host,
                apiNonce: CoinsnapCoreAdmin.nonce || ''
            }, function(response) {
                if (response.data && response.data.url) {
                    if (response.data.popup) {
                        var popup = window.open(response.data.url, 'btcpay_voting_auth', 'width=760,height=640,scrollbars=yes,resizable=yes');
                        var handler = function(event) {
                            if (event.origin !== window.location.origin) return;
                            if (!event.data || event.data.type !== 'coinsnap_voting_btcpay_auth') return;
                            window.removeEventListener('message', handler);
                            var $apiKey = $('input[name$="[btcpay_api_key]"]');
                            var $storeId = $('input[name$="[btcpay_store_id]"]');
                            if (event.data.apiKey && $apiKey.length) {
                                $apiKey.val(event.data.apiKey).css({'border-color': '#00a32a', 'box-shadow': '0 0 0 1px #00a32a'});
                            }
                            if (event.data.storeId && $storeId.length) {
                                $storeId.val(event.data.storeId).css({'border-color': '#00a32a', 'box-shadow': '0 0 0 1px #00a32a'});
                            }
                            if (popup && !popup.closed) { popup.close(); }
                        };
                        window.addEventListener('message', handler);
                    } else {
                        window.location = response.data.url;
                    }
                }
            }).fail(function() {
                alert('Error processing your request. Please verify your BTCPay Server URL.');
            });
        });
    });
})(jQuery);
