jQuery(document).ready(function($) {

    if(!$('#blur-overlay-outer').length){
        $('body').append('<div id="blur-overlay-outer"></div><div id="coinsnap-popup-outer"></div>');        
    }
    
    // ============================================================
    // HANDLE PAYMENT REDIRECT FROM COINSNAP
    // Check if user was just redirected from payment (payment_initiated=1)
    // ============================================================
    const urlParams = new URLSearchParams(window.location.search);
    const paymentInitiated = urlParams.get('payment_initiated');
    const pollIdParam = urlParams.get('poll_id');
    const optionIdParam = urlParams.get('option_id');
    const refIdParam = urlParams.get('ref_id');
    
    console.log('DEBUG: URL params - payment_initiated:', paymentInitiated, 'poll_id:', pollIdParam, 'option_id:', optionIdParam, 'ref_id:', refIdParam);
    
    // Try to get invoice_id from URL first, then from localStorage
    let invoiceIdParam = urlParams.get('invoice_id');
    // Also try Coinsnap's naming convention (invoiceId with capital I)
    if (!invoiceIdParam) {
        invoiceIdParam = urlParams.get('invoiceId');
    }
    if (!invoiceIdParam) {
        try {
            const savedInvoice = JSON.parse(localStorage.getItem('coinsnap_invoice_voting') || '{}');
            invoiceIdParam = savedInvoice.id;
            console.log('DEBUG: Got invoice_id from localStorage:', invoiceIdParam);
        } catch (e) {
            console.error('Error reading invoice from localStorage:', e);
        }
    }
    
    console.log('DEBUG: Final check - paymentInitiated:', paymentInitiated, 'pollIdParam:', pollIdParam, 'invoiceIdParam:', invoiceIdParam, 'optionIdParam:', optionIdParam);
    
    // If payment_initiated is present and we have poll/option, proceed even without invoice_id
    // We'll try to get invoice_id from localStorage or let backend handle it
    if (paymentInitiated === '1' && pollIdParam && optionIdParam && invoiceIdParam) {
        console.log('User returned from Coinsnap payment - invoiceId:', invoiceIdParam, 'pollId:', pollIdParam, 'optionId:', optionIdParam);
        
        // Show payment success notification
        const successNotification = document.createElement('div');
        successNotification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #4CAF50;
            color: white;
            padding: 16px 24px;
            border-radius: 4px;
            font-size: 14px;
            z-index: 9999;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            animation: slideIn 0.3s ease-out;
        `;
        successNotification.textContent = '✓ Payment successful! Recording your vote...';
        document.body.appendChild(successNotification);
        
        // Remove notification after 5 seconds
        setTimeout(() => {
            successNotification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => successNotification.remove(), 300);
        }, 5000);
        
        // Clean up URL params
        window.history.replaceState({}, document.title, window.location.pathname);
        
        console.log('Calling record_payment with invoice_id:', invoiceIdParam, 'poll_id:', pollIdParam, 'option_id:', optionIdParam);
        
        // Call record_payment endpoint to manually record the vote
        fetch(Coinsnap_Bitcoin_Voting_sharedData.rest_url.replace(/\/$/, '') + '/voting/v1/record-payment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': Coinsnap_Bitcoin_Voting_sharedData?.nonce || ''
            },
            body: JSON.stringify({
                invoice_id: invoiceIdParam,
                poll_id: parseInt(pollIdParam),
                option_id: parseInt(optionIdParam)
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Vote recording response:', data);
            
            if (data.status === 'already_recorded' || data.message === 'Vote already recorded') {
                console.log('✓ Vote was already recorded (from webhook or previous request)');
                // Still reload to show results
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else if (data.status === 'completed' || data.message === 'Vote recorded') {
                console.log('✓ Vote successfully recorded!');
                // Reload to show updated results
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                console.warn('Unexpected response:', data);
                // Still try to reload
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        })
        .catch(err => {
            console.error('Error recording vote:', err);
            // Reload anyway to show whatever results exist
            setTimeout(() => {
                location.reload();
            }, 2000);
        });
    }
    
    // Check if webhook has recorded the payment by looking at stored invoice cookie
    // This runs when user manually returns to the poll page
    const storedInvoice = localStorage.getItem('coinsnap_last_payment');
    if (storedInvoice) {
        try {
            const paymentData = JSON.parse(storedInvoice);
            console.log('Checking if payment was recorded:', paymentData);
            
            // Small delay to ensure database is updated
            setTimeout(() => {
                // Force refresh to show new results
                location.reload();
            }, 1000);
            
            localStorage.removeItem('coinsnap_last_payment');
        } catch (e) {
            console.error('Error parsing stored payment data:', e);
        }
    }
    
    if (document.getElementsByClassName('coinsnap-bitcoin-voting-form')?.length > 0) {
        
        var overlayContainer = $('.blur-overlay.coinsnap-bitcoin-voting').detach();
        $('#blur-overlay-outer').append(overlayContainer);  
        var qrContainer = $('.qr-container.coinsnap-bitcoin-voting').detach();
        $('#coinsnap-popup-outer').append(qrContainer);

        //fetchCoinsnapExchangeRates().then(rates => {
            addWindowListeners();
            const votingForms = document.getElementsByClassName('coinsnap-bitcoin-voting-form');
            for (let i = 0; i < votingForms.length; i++) {
                const votingForm = votingForms[i];
                const pollId = votingForm.dataset.pollId;
                const amount = votingForm.dataset.pollAmount;
                const amountFiat = votingForm.dataset.pollAmountfiat;
                const currency = votingForm.dataset.pollCurrency;
                const donorInfo = votingForm.dataset.donorInfo;
                popupButtonListener(pollId, amount, amountFiat, currency, donorInfo);
            }
        //});
        

        const fetchResultsFromDb = (pollId, votingForm) => {
            fetch(`${Coinsnap_Bitcoin_Voting_sharedData?.rest_url?.replace(/\/$/, '') || ''}/voting/v1/voting_results/${pollId}`, {
                    headers: { 'X-WP-Nonce': Coinsnap_Bitcoin_Voting_sharedData?.nonce || '' }
                })
                .then(response => response.json())
                .then(data => {
                    const votesDb = data.results;
                    const votesLen = votesDb.length;
                    let votes = {};
                    votesDb.forEach(result => {
                        const vote = parseInt(result.option_id);
                        votes[vote] = (votes[vote] || 0) + 1;
                    });
                    const maxVote = Math.max(...Object.values(votes));
                    const maxVoteOption = Object.keys(votes).find(key => votes[key] === maxVote);

                    // Safely highlight the option with max votes
                    if (maxVoteOption !== undefined) {
                        const maxBar = votingForm.querySelector(`.voting-progress-bar[data-option='${maxVoteOption}']`);
                        if (maxBar) {
                            maxBar.style['background-color'] = '#f7a70a';
                        }
                    }
                    document.getElementById(`total-votes${pollId}`).textContent = `${votesDb.length}`;

                    Object.keys(votes).forEach(opt => {
                        let percentage = votesLen > 0 ? (votes[opt] / votesLen) * 100 : 0;
                        if (percentage > 0) {
                            const percentageSpan = votingForm.querySelector(`.voting-progress-percentage[data-option='${opt}']`);
                            if (percentageSpan) {
                                percentageSpan.textContent = percentage.toFixed(1) + "%";
                            }
                        }
                        const progressBar = votingForm.querySelector(`.voting-progress-bar[data-option='${opt}']`);
                        if (progressBar) {
                            progressBar.style.width = percentage + "%";
                        }
                        const voteCount = votingForm.querySelector(`.vote-count[data-option='${opt}']`);
                        if (voteCount) {
                            voteCount.textContent = votes[opt];
                        }
                    });
                });
        };

        const showResultsFn = (pollId, votingForm) => {
            const returnButton = document.getElementById(`return-button${pollId}`);
            votingForm.querySelector(".poll-options").style.display = "none";
            votingForm.querySelector(".poll-results").style.display = "block";
            const oneVote = votingForm.dataset.oneVote;
            const voted = getCookie('coinsnap_poll_' + pollId);
            if (oneVote && voted) {
                returnButton.classList.remove('return-buton-visible');
            } else {
                returnButton.classList.add('return-buton-visible');
            }
            fetchResultsFromDb(pollId, votingForm);
        };

        for (let i = 0; i < votingForms.length; i++) {
            const votingForm = votingForms[i];
            const pollId = votingForm.dataset.pollId;

            if (pollId) {// Check cookie and show results if user voted already
                const voted = getCookie('coinsnap_poll_' + pollId);
                if (voted) {
                    showResultsFn(pollId, votingForm);
                }
            }

            const returnButton = document.getElementById(`return-button${pollId}`);
            if (returnButton) {
                returnButton.addEventListener("click", function () {
                    votingForm.querySelector(".poll-options").style.display = "flex";
                    votingForm.querySelector(".poll-results").style.display = "none";
                    returnButton.classList.remove('return-buton-visible');
                });
            }

            const checkResults = document.getElementById(`cbv-check-results${pollId}`);
            if (checkResults) {
                checkResults.addEventListener("click", function () {
                    showResultsFn(pollId, votingForm);
                });
            }

            // In case poll is closed
            const pollResults = document.getElementById(`poll-results${pollId}`);
            if (pollResults) {
                const endDate = new Date(pollResults.dataset.endDate);
                const nowDate = new Date();
                const pollId = pollResults.dataset.pollId;

                if (endDate < nowDate) {
                    fetchResultsFromDb(pollId, votingForm);
                }
            }
        }

        for (let i = 0; i < votingForms.length; i++) {
            const votingForm = votingForms[i];
            votingForm.querySelectorAll('.poll-option').forEach(button => {
                const donorInfo = votingForm.dataset.donorInfo;
                const amount = votingForm.dataset.pollAmount;
                const amountFiat = votingForm.dataset.pollAmountfiat;
                const currency = votingForm.dataset.pollCurrency;
                const pollId = votingForm.dataset.pollId;

                addVotingPopupListener(button, donorInfo, amount, amountFiat, currency, pollId);
            });

        }
    }
    
    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        @keyframes slideUp {
            from {
                transform: translateY(0);
                opacity: 1;
            }
            to {
                transform: translateY(-100%);
                opacity: 0;
            }
        }
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});