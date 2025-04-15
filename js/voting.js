jQuery(document).ready(function ($) {

    if (document.getElementsByClassName('coinsnap-bitcoin-voting-form')?.length > 0) {

        fetchCoinsnapExchangeRates().then(rates => {
            addWindowListeners()
            const votingForms = document.getElementsByClassName('coinsnap-bitcoin-voting-form');
            for (let i = 0; i < votingForms.length; i++) {
                const votingForm = votingForms[i];
                const pollId = votingForm.dataset.pollId
                const amount = votingForm.dataset.pollAmount
                const donorInfo = votingForm.dataset.donorInfo
                popupButtonListener(rates, pollId, amount, donorInfo)
            }
        })

        const fetchResultsFromDb = (pollId, votingForm) => {
            fetch(`/wp-json/voting/v1/voting_results/${pollId}`)
                .then(response => response.json())
                .then(data => {
                    const votesDb = data.results
                    const votesLen = votesDb.length
                    let votes = {};
                    votesDb.forEach(result => {
                        const vote = parseInt(result.option_id)
                        votes[vote] = (votes[vote] || 0) + 1;
                    });
                    const maxVote = Math.max(...Object.values(votes))
                    const maxVoteOption = Object.keys(votes).find(key => votes[key] === maxVote);

                    votingForm.querySelector(`.voting-progress-bar[data-option='${maxVoteOption}']`).style['background-color'] = '#f7a70a';
                    document.getElementById(`total-votes${pollId}`).textContent = `${votesDb.length}`;

                    Object.keys(votes).forEach(opt => {
                        let percentage = votesLen > 0 ? (votes[opt] / votesLen) * 100 : 0;
                        if (percentage > 0) {
                            const percentageSpan = votingForm.querySelector(`.voting-progress-percentage[data-option='${opt}']`);

                            percentageSpan.textContent = percentage.toFixed(1) + "%";
                        }
                        votingForm.querySelector(`.voting-progress-bar[data-option='${opt}']`).style.width = percentage + "%";
                        votingForm.querySelector(`.vote-count[data-option='${opt}']`).textContent = votes[opt];
                    });
                })
        }

        const showResultsFn = (pollId, votingForm) => {
            const returnButton = document.getElementById(`return-button${pollId}`)
            votingForm.querySelector(".poll-options").style.display = "none";
            votingForm.querySelector(".poll-results").style.display = "block";
            const oneVote = votingForm.dataset.oneVote
            const voted = getCookie('coinsnap_poll_' + pollId)
            if (oneVote && voted) {
                returnButton.classList.remove('return-buton-visible')
            } else {
                returnButton.classList.add('return-buton-visible')
            }
            fetchResultsFromDb(pollId, votingForm)
        }

        const votingForms = document.getElementsByClassName('coinsnap-bitcoin-voting-form');

        for (let i = 0; i < votingForms.length; i++) {
            const votingForm = votingForms[i];
            const pollId = votingForm.dataset.pollId

            if (pollId) {// Check cookie and show results if user voted already
                const voted = getCookie('coinsnap_poll_' + pollId)
                if (voted) {
                    showResultsFn(pollId, votingForm)
                }
            }

            const returnButton = document.getElementById(`return-button${pollId}`)
            if (returnButton) {
                returnButton.addEventListener("click", function () {
                    votingForm.querySelector(".poll-options").style.display = "flex";
                    votingForm.querySelector(".poll-results").style.display = "none";
                    returnButton.classList.remove('return-buton-visible')
                });
            }


            const checkResults = document.getElementById(`check-results${pollId}`);
            if (checkResults) {
                checkResults.addEventListener("click", function () {
                    showResultsFn(pollId, votingForm)
                });
            }

            // In case poll is closed
            const pollResults = document.getElementById(`poll-results${pollId}`);
            if (pollResults) {
                const endDate = new Date(pollResults.dataset.endDate)
                const nowDate = new Date()
                const pollId = pollResults.dataset.pollId

                if (endDate < nowDate) {
                    fetchResultsFromDb(pollId, votingForm)
                }
            }
        }

        for (let i = 0; i < votingForms.length; i++) {
            const votingForm = votingForms[i];
            votingForm.querySelectorAll('.poll-option').forEach(button => {
                const donorInfo = votingForm.dataset.donorInfo
                const amount = votingForm.dataset.pollAmount
                const pollId = votingForm.dataset.pollId

                addVotingPopupListener(button, donorInfo, amount, pollId)
            });

        }
    }
});