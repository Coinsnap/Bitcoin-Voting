jQuery(document).ready(function($) {

    if(!$('#blur-overlay-outer').length){
        $('body').append('<div id="blur-overlay-outer"></div><div id="coinsnap-popup-outer"></div>');        
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
            const resultsUrl = (typeof Coinsnap_Bitcoin_Voting_sharedData !== 'undefined' && Coinsnap_Bitcoin_Voting_sharedData.resultsUrl)
                ? Coinsnap_Bitcoin_Voting_sharedData.resultsUrl
                : '/wp-json/voting/v1/voting_results/';
            fetch(`${resultsUrl}${encodeURIComponent(pollId)}`)
                .then(response => response.json())
                .then(data => {
                    // The server returns only the number of votes per option.
                    const votesLen = parseInt(data.total, 10) || 0;
                    let votes = {};
                    (data.results || []).forEach(result => {
                        votes[parseInt(result.option_id, 10)] = parseInt(result.votes, 10) || 0;
                    });
                    if (votesLen === 0) {
                        document.getElementById(`total-votes${pollId}`).textContent = '0';
                        return;
                    }
                    const maxVote = Math.max(...Object.values(votes));
                    const maxVoteOption = Object.keys(votes).find(key => votes[key] === maxVote);

                    votingForm.querySelector(`.voting-progress-bar[data-option='${maxVoteOption}']`).style['background-color'] = '#f7a70a';
                    document.getElementById(`total-votes${pollId}`).textContent = `${votesLen}`;

                    Object.keys(votes).forEach(opt => {
                        let percentage = votesLen > 0 ? (votes[opt] / votesLen) * 100 : 0;
                        if (percentage > 0) {
                            const percentageSpan = votingForm.querySelector(`.voting-progress-percentage[data-option='${opt}']`);

                            percentageSpan.textContent = percentage.toFixed(1) + "%";
                        }
                        votingForm.querySelector(`.voting-progress-bar[data-option='${opt}']`).style.width = percentage + "%";
                        votingForm.querySelector(`.vote-count[data-option='${opt}']`).textContent = votes[opt];
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

            const checkResults = document.getElementById(`check-results${pollId}`);
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
});