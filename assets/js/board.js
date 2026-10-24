(function () {
    'use strict';

    var cfg = window.scdpBoard || {};
    var feedback = document.getElementById('scdp-feedback');
    var drag = { card: null, from: null };

    function showFeedback(msg, kind, undoFn) {
        if (!feedback) {
            return;
        }
        feedback.className = 'scdp-feedback' + (kind === 'error' ? ' is-error' : '');
        feedback.textContent = '';

        var span = document.createElement('span');
        span.textContent = msg;
        feedback.appendChild(span);

        if (undoFn) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = cfg.undo || 'Undo';
            btn.addEventListener('click', function () {
                feedback.style.display = 'none';
                undoFn();
            });
            feedback.appendChild(btn);
        }

        feedback.style.display = 'flex';
        clearTimeout(feedback._timer);
        feedback._timer = setTimeout(function () {
            feedback.style.display = 'none';
        }, undoFn ? 6000 : 2600);
    }

    function send(data) {
        var body = Object.keys(data).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
        }).join('&');

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (r) {
            return r.json();
        }).then(function (j) {
            if (j && j.success) {
                return j.data || {};
            }
            throw new Error((j && j.data && j.data.message) || cfg.failed);
        });
    }

    function column(status) {
        return document.querySelector('.scdp-col[data-status="' + status + '"]');
    }

    function recount(col) {
        if (!col) {
            return;
        }
        var cards = col.querySelectorAll('.scdp-card');
        var badge = col.querySelector('.scdp-n');
        if (badge) {
            badge.textContent = cards.length;
        }
        var none = col.querySelector('.scdp-none');
        if (cards.length && none) {
            none.remove();
        } else if (!cards.length && !none) {
            var empty = document.createElement('div');
            empty.className = 'scdp-none';
            empty.textContent = cfg.empty || 'Nothing here';
            col.appendChild(empty);
        }
    }

    function place(card, status) {
        var col = column(status);
        if (!col || !card) {
            return;
        }
        var previous = card.parentElement;
        col.appendChild(card);
        card.setAttribute('data-status', status);
        recount(previous);
        recount(col);
    }

    function move(card, toStatus, silent) {
        var fromStatus = card.getAttribute('data-status');
        if (fromStatus === toStatus) {
            return;
        }
        if (toStatus === 'publish' && !silent && !window.confirm(cfg.confirmPublish || 'Publish now?')) {
            return;
        }

        showFeedback(cfg.moving);
        place(card, toStatus);

        send({ action: cfg.action, nonce: cfg.nonce, post_id: card.getAttribute('data-post-id'), status: toStatus })
            .then(function (d) {
                if (d.status && d.status !== toStatus) {
                    place(card, d.status);
                }
                if (d.when) {
                    var when = card.querySelector('.scdp-when');
                    if (when) {
                        when.textContent = d.when;
                        when.classList.remove('is-miss');
                    }
                }
                // A card only reads as missed while it sits in Scheduled with a
                // past date, and the server pushes that date out on the way in.
                // After any successful move it can't be missed.
                card.classList.remove('is-miss');
                if (silent) {
                    showFeedback(cfg.moved);
                    return;
                }
                showFeedback(cfg.moved, 'success', function () {
                    move(card, fromStatus, true);
                });
            })
            .catch(function (e) {
                place(card, fromStatus);
                showFeedback(e.message || cfg.failed, 'error');
            });
    }

    function clearOver() {
        var over = document.querySelectorAll('.scdp-col.is-over');
        for (var i = 0; i < over.length; i++) {
            over[i].classList.remove('is-over');
        }
    }

    document.addEventListener('dragstart', function (e) {
        var card = e.target.closest ? e.target.closest('.scdp-card') : null;
        if (!card || card.getAttribute('draggable') === 'false') {
            return;
        }
        drag.card = card;
        drag.from = card.getAttribute('data-status');
        card.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try {
            e.dataTransfer.setData('text/plain', card.getAttribute('data-post-id'));
        } catch (err) { /* older browsers */ }
    });

    document.addEventListener('dragend', function () {
        if (drag.card) {
            drag.card.classList.remove('is-dragging');
        }
        clearOver();
        drag.card = null;
        drag.from = null;
    });

    document.addEventListener('dragover', function (e) {
        if (!drag.card) {
            return;
        }
        var col = e.target.closest ? e.target.closest('.scdp-col') : null;
        if (!col || col.getAttribute('data-droppable') === '0') {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (!col.classList.contains('is-over')) {
            clearOver();
            col.classList.add('is-over');
        }
    });

    document.addEventListener('drop', function (e) {
        if (!drag.card) {
            return;
        }
        var col = e.target.closest ? e.target.closest('.scdp-col') : null;
        if (!col || col.getAttribute('data-droppable') === '0') {
            return;
        }
        e.preventDefault();
        clearOver();
        move(drag.card, col.getAttribute('data-status'));
    });

    // Keyboard: focus a card, then ArrowLeft / ArrowRight to shift columns.
    document.addEventListener('keydown', function (e) {
        var card = document.activeElement;
        if (!card || !card.classList || !card.classList.contains('scdp-card')) {
            return;
        }
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
            return;
        }

        var cols = Array.prototype.slice.call(document.querySelectorAll('.scdp-col'));
        var index = cols.indexOf(card.closest('.scdp-col'));
        var next = e.key === 'ArrowRight' ? index + 1 : index - 1;
        if (index < 0 || next < 0 || next >= cols.length) {
            return;
        }
        if (cols[next].getAttribute('data-droppable') === '0') {
            return;
        }

        e.preventDefault();
        move(card, cols[next].getAttribute('data-status'));
        card.focus();
    });
})();
