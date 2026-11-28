(function() {
    function attach() {
        const video = document.getElementById('sam-player');
        const host = document.querySelector('[data-sa-checkpoints]');
        if (!video || !host) {
            return;
        }
        const rows = [...host.querySelectorAll('[data-timing]')];
        const auto = document.getElementById('sam-autonext');
        const message = host.querySelector('[data-sa-checkpoint-notice]');
        const show = (row, pause) => {
            if (!row.hidden) {
                return;
            }
            row.hidden = false;
            host.hidden = false;
            message.textContent = row.dataset.title;
            if (pause && !video.paused) {
                video.pause();
            }
        };
        let lastTime = 0;
        rows.sort((a, b) => Number(a.dataset.seconds) - Number(b.dataset.seconds));
        video.addEventListener('timeupdate', () => {
            const current = video.currentTime;
            const due = rows.find(row => row.hidden && row.dataset.timing === 'during' &&
                lastTime < Number(row.dataset.seconds) && current >= Number(row.dataset.seconds));
            if (due && !video.seeking) {
                show(due, true);
            }
            lastTime = current;
        });
        video.addEventListener('ended', () => {
            const end = rows.filter(row => row.dataset.timing === 'after');
            if (end.length && auto) {
                auto.checked = false;
            }
            end.forEach(row => show(row, false));
        }, true);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attach, {once: true});
    } else {
        attach();
    }
})();
