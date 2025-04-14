jQuery(document).ready(function ($) {

    if (document.getElementById('coinsnap-bitcoin-voting-form')) {

        if (document.getElementById('coinsnap-bitcoin-voting-form')) {
            fetchCoinsnapExchangeRates().then(rates => {
                const pollId = document.querySelector('#coinsnap-bitcoin-voting-form').dataset.pollId
                const amount = document.querySelector('#coinsnap-bitcoin-voting-form').dataset.pollAmount
                const donorInfo = document.querySelector('#coinsnap-bitcoin-voting-form')?.dataset.donorInfo
                addWindowListeners()
                popupButtonListener(rates, pollId, amount, donorInfo)
            })
        }

        const returnButton = document.getElementById('return-button')
        if (returnButton) {
            returnButton.addEventListener("click", function () {
                document.querySelector(".poll-options").style.display = "flex";
                document.querySelector(".poll-results").style.display = "none";
                returnButton.classList.remove('return-buton-visible')
            });
        }

        const fetchResultsFromDb = (pollId) => {
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

                    document.querySelector(`.voting-progress-bar[data-option='${maxVoteOption}']`).style['background-color'] = '#f7a70a';
                    document.getElementById("total-votes").textContent = `${votesDb.length}`;

                    Object.keys(votes).forEach(opt => {
                        let percentage = votesLen > 0 ? (votes[opt] / votesLen) * 100 : 0;
                        if (percentage > 0) {
                            const percentageSpan = document.querySelector(`.voting-progress-percentage[data-option='${opt}']`);

                            percentageSpan.textContent = percentage.toFixed(1) + "%";
                        }
                        document.querySelector(`.voting-progress-bar[data-option='${opt}']`).style.width = percentage + "%";
                        document.querySelector(`.vote-count[data-option='${opt}']`).textContent = votes[opt];
                    });
                })
        }

        const showResultsFn = (pollId) => {
            document.querySelector(".poll-options").style.display = "none";
            document.querySelector(".poll-results").style.display = "block";
            const oneVote = document.querySelector('#coinsnap-bitcoin-voting-form')?.dataset.oneVote
            const voted = getCookie('coinsnap_poll_' + pollId)
            if (oneVote && voted) {
                returnButton.classList.remove('return-buton-visible')
            } else {
                returnButton.classList.add('return-buton-visible')
            }
            fetchResultsFromDb(pollId)
        }

        const pollId = document.querySelector('#coinsnap-bitcoin-voting-form')?.dataset.pollId

        if (pollId) {// Check cookie and show results if user voted already
            const voted = getCookie('coinsnap_poll_' + pollId)
            if (voted) {
                showResultsFn(pollId)
            }
        }

        const checkResults = document.getElementById("check-results");
        if (checkResults) {
            checkResults.addEventListener("click", function () {
                showResultsFn(pollId)
            });
        }

        // In case poll is closed
        const pollResults = document.getElementById("poll-results");
        if (pollResults) {
            const endDate = new Date(pollResults.dataset.endDate)
            const nowDate = new Date()
            const pollId = pollResults.dataset.pollId

            if (endDate < nowDate) {
                fetchResultsFromDb(pollId)
            }
        }

        // On vote click
        document.querySelectorAll(".poll-option").forEach(button => {
            const donorInfo = document.querySelector('#coinsnap-bitcoin-voting-form')?.dataset.donorInfo
            addVotingPopupListener(button, donorInfo)
        });
    }
});