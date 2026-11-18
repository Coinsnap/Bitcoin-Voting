(function ($) {
  // Wait until the DOM is fully loaded
  $(document).ready(function () {
    const $providerSelector = $('#provider');
    const $coinsnapWrapper = $('#coinsnap-settings-wrapper');
    const $btcpayWrapper = $('#btcpay-settings-wrapper');

    const tabs = document.querySelectorAll(".nav-tab");
    const contents = document.querySelectorAll(".tab-content");

    tabs.forEach(tab => {
      tab.addEventListener("click", function (e) {
        e.preventDefault();
        tabs.forEach(t => t.classList.remove("nav-tab-active"));
        contents.forEach(c => c.classList.remove("active"));
        tab.classList.add("nav-tab-active");
        const target = tab.getAttribute("data-tab");
        document.getElementById(target).classList.add("active");
        localStorage.setItem('activeTab', target);
      });
    });
    const restoreTabs = () => {
      const savedTab = localStorage.getItem('activeTab');
      const activeTab = document.querySelector(`.nav-tab[data-tab="${savedTab}"]`);
      if (activeTab) {
        activeTab.click()
      }
    }
    restoreTabs();

    // API calls and webhook registration happen on the server only.
    function toggleProviderSettings() {
      if (!$providerSelector || !$providerSelector.length) {
        return;
      }
      const selectedProvider = $providerSelector?.val();
      $coinsnapWrapper.toggle(selectedProvider === 'coinsnap');
      $btcpayWrapper.toggle(selectedProvider === 'btcpay');
    }

    toggleProviderSettings();

    $providerSelector.on('change', toggleProviderSettings);

    //  Voting poll setttings
    
    function toggleDonorFields() {
                    if ($('input[name="coinsnap_bitcoin_voting_polls_collect_donor_info"]').is(':checked')) {
                        $('#donor-info-fields').show();
                        $('#coinsnap_bitcoin_voting_polls_custom_field_name').prop('required', true);
                    } else {
                        $('#donor-info-fields').hide();
                        $('#coinsnap_bitcoin_voting_polls_custom_field_name').prop('required', false);
                    }
                }
    
    if($('input[name="coinsnap_bitcoin_voting_polls_collect_donor_info"]').length){
        $('input[name="coinsnap_bitcoin_voting_polls_collect_donor_info"]').change(toggleDonorFields);
        toggleDonorFields();
    }
    
    $('#coinsnap_bitcoin_voting_btcpay_wizard_button').click(function(e) {
        e.preventDefault();
        const host = $('#btcpay_url').val();
	if (isVotingValidUrl(host)) {
            let data = {
                'action': 'coinsnap_bitcoin_voting_btcpay_apiurl_handler',
                'host': host,
                'apiNonce': coinsnap_bitcoin_voting_ajax.nonce
            };
            
            $.post(coinsnap_bitcoin_voting_ajax.ajax_url, data, function(response) {
                if (response.data.url) {
                    window.location = response.data.url;
		}
            }).fail( function() {
		alert('Error processing your request. Please make sure to enter a valid BTCPay Server instance URL.')
            });
	}
        else {
            alert('Please enter a valid url including https:// in the BTCPay Server URL input field.')
        }
    });
    
    if($('#coinsnap_bitcoin_voting_polls_currency').length){
        
        setStep();
        $('#coinsnap_bitcoin_voting_polls_currency').change(
            function(){
                setStep();
            }    
        );
        
    }
    
    if($('.coinsnapConnectionStatus').length){
        
        
        let ajaxurl = coinsnap_bitcoin_voting_ajax.ajax_url;
        let data = {
            action: 'coinsnap_bitcoin_voting_connection_handler',
            apiNonce: coinsnap_bitcoin_voting_ajax.nonce,
            apiPost: coinsnap_bitcoin_voting_ajax.post
        };

        jQuery.post( ajaxurl, data, function( response ){

            connectionCheckResponse = $.parseJSON(response);
            let resultClass = (connectionCheckResponse.result === true)? 'success' : 'error';
            $('.coinsnapConnectionStatus').html('<span class="'+resultClass+'">'+ connectionCheckResponse.message +'</span>');
            
        });
    }
    
  });
  
    function setStep(){
        let step = 0.01;
        let currency = $('#coinsnap_bitcoin_voting_polls_currency').val();
        if(currency === 'RUB' || currency === 'JPY' || currency === 'SATS'){
            step = 1;
        }
        $('#coinsnap_bitcoin_voting_polls_amount').attr('step', step);
    }
  
    function isVotingValidUrl(serverUrl) {
        if(serverUrl.indexOf('http') > -1){
            try {
                const url = new URL(serverUrl);
                if (url.protocol !== 'https:' && url.protocol !== 'http:') {
                    return false;
                }
            }
            catch (e) {
                return false;
            }
            return true;
        }
        else {
            return false;
        }
    }
  
})(jQuery);
