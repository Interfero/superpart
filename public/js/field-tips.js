(function () {
    const GAP = 8;
    const VIEWPORT_PAD = 12;

    function positionTip(wrap) {
        const btn = wrap.querySelector('.field-tip-trigger');
        const tip = wrap.querySelector('.field-tip-popover');
        if (!btn || !tip) {
            return;
        }

        tip.classList.remove('hidden');
        tip.style.position = 'fixed';
        tip.style.zIndex = '99999';
        tip.style.visibility = 'hidden';
        tip.style.left = '0';
        tip.style.top = '0';
        tip.style.transform = 'none';

        const btnRect = btn.getBoundingClientRect();
        const tipRect = tip.getBoundingClientRect();

        let left = btnRect.right + GAP;
        let top = btnRect.top + btnRect.height / 2 - tipRect.height / 2;

        if (left + tipRect.width > window.innerWidth - VIEWPORT_PAD) {
            left = btnRect.left - tipRect.width - GAP;
        }

        if (left < VIEWPORT_PAD) {
            left = VIEWPORT_PAD;
        }

        if (top < VIEWPORT_PAD) {
            top = VIEWPORT_PAD;
        }

        if (top + tipRect.height > window.innerHeight - VIEWPORT_PAD) {
            top = window.innerHeight - tipRect.height - VIEWPORT_PAD;
        }

        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
        tip.style.visibility = 'visible';
    }

    function hideTip(wrap) {
        const tip = wrap.querySelector('.field-tip-popover');
        if (!tip) {
            return;
        }

        tip.classList.add('hidden');
        tip.style.position = '';
        tip.style.left = '';
        tip.style.top = '';
        tip.style.transform = '';
        tip.style.zIndex = '';
        tip.style.visibility = '';
    }

    function initFieldTips() {
        document.querySelectorAll('.field-tip').forEach(function (wrap) {
            if (wrap.dataset.fieldTipBound === '1') {
                return;
            }

            wrap.dataset.fieldTipBound = '1';

            wrap.addEventListener('mouseenter', function () {
                positionTip(wrap);
            });

            wrap.addEventListener('mouseleave', function () {
                hideTip(wrap);
            });

            wrap.addEventListener('focusin', function () {
                positionTip(wrap);
            });

            wrap.addEventListener('focusout', function (event) {
                if (wrap.contains(event.relatedTarget)) {
                    return;
                }

                hideTip(wrap);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFieldTips);
    } else {
        initFieldTips();
    }

    window.addEventListener('resize', function () {
        document.querySelectorAll('.field-tip').forEach(function (wrap) {
            const tip = wrap.querySelector('.field-tip-popover');
            if (tip && !tip.classList.contains('hidden')) {
                positionTip(wrap);
            }
        });
    });

    window.addEventListener(
        'scroll',
        function () {
            document.querySelectorAll('.field-tip').forEach(function (wrap) {
                const tip = wrap.querySelector('.field-tip-popover');
                if (tip && !tip.classList.contains('hidden')) {
                    positionTip(wrap);
                }
            });
        },
        true
    );
})();
