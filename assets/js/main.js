// ============================================================
// Sri Lanka Online Voting System - Main JavaScript
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // ---- OTP Input Auto-Focus ----
    const otpInputs = document.querySelectorAll('.otp-inputs input');
    otpInputs.forEach((input, index) => {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 1);
            if (this.value && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
            assembleOTP();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !this.value && index > 0) {
                otpInputs[index - 1].focus();
            }
        });
        input.addEventListener('paste', function (e) {
            e.preventDefault();
            const pasted = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
            pasted.split('').forEach((char, i) => {
                if (otpInputs[index + i]) otpInputs[index + i].value = char;
            });
            assembleOTP();
        });
    });

    function assembleOTP() {
        const hidden = document.getElementById('otp_hidden');
        if (hidden && otpInputs.length === 6) {
            hidden.value = Array.from(otpInputs).map(i => i.value).join('');
        }
    }

    // ---- Candidate Selection ----
    const candidateCards = document.querySelectorAll('.candidate-card');
    candidateCards.forEach(card => {
        card.addEventListener('click', function () {
            candidateCards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            const radio = this.querySelector('.candidate-radio');
            if (radio) radio.checked = true;
            const confirmBtn = document.getElementById('confirm-vote-btn');
            if (confirmBtn) confirmBtn.disabled = false;
        });
    });

    // ---- Vote Confirm Modal ----
    const confirmVoteBtn = document.getElementById('confirm-vote-btn');
    const voteModal      = document.getElementById('vote-modal');
    const cancelVoteBtn  = document.getElementById('cancel-vote-btn');
    const voteForm       = document.getElementById('vote-form');

    if (confirmVoteBtn && voteModal) {
        confirmVoteBtn.addEventListener('click', function () {
            const selected = document.querySelector('.candidate-card.selected');
            if (!selected) return;
            const name  = selected.querySelector('h3')?.textContent || '';
            const party = selected.querySelector('.candidate-party')?.textContent || '';
            const nameEl  = document.getElementById('modal-candidate-name');
            const partyEl = document.getElementById('modal-candidate-party');
            if (nameEl)  nameEl.textContent  = name;
            if (partyEl) partyEl.textContent = party;
            voteModal.classList.add('open');
        });
    }
    if (cancelVoteBtn && voteModal) {
        cancelVoteBtn.addEventListener('click', () => voteModal.classList.remove('open'));
        voteModal.addEventListener('click', e => { if (e.target === voteModal) voteModal.classList.remove('open'); });
    }

    // ---- Animate result bars on load ----
    const resultBars = document.querySelectorAll('.result-bar-fill');
    resultBars.forEach(bar => {
        const target = bar.getAttribute('data-width') || '0';
        bar.style.width = '0%';
        setTimeout(() => { bar.style.width = target + '%'; }, 200);
    });

    // ---- Sidebar toggle (mobile) ----
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar       = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // ---- Auto-dismiss alerts ----
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // ---- Confirm delete / action dialogs ----
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.getAttribute('data-confirm'))) e.preventDefault();
        });
    });

    // ---- NIC format hint ----
    const nicInput = document.getElementById('nic');
    if (nicInput) {
        nicInput.addEventListener('input', function () {
            const val = this.value.trim();
            const hint = document.getElementById('nic-hint');
            if (!hint) return;
            if (/^[0-9]{9}[VXvx]$/.test(val) || /^[0-9]{12}$/.test(val)) {
                hint.textContent = 'Valid NIC format';
                hint.style.color = '#2e7d32';
            } else if (val.length > 0) {
                hint.textContent = 'Format: 9 digits + V/X  or  12 digits';
                hint.style.color = '#c62828';
            } else {
                hint.textContent = '';
            }
        });
    }

    // ---- Password strength indicator ----
    const pwInput = document.getElementById('password');
    if (pwInput) {
        pwInput.addEventListener('input', function () {
            const bar = document.getElementById('pw-strength-bar');
            if (!bar) return;
            const v = this.value;
            let score = 0;
            if (v.length >= 8)          score++;
            if (/[A-Z]/.test(v))        score++;
            if (/[0-9]/.test(v))        score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            const colors = ['#e0e0e0', '#c62828', '#f57f17', '#1565c0', '#2e7d32'];
            const labels = ['',        'Weak',    'Fair',    'Good',    'Strong'];
            bar.style.width     = (score * 25) + '%';
            bar.style.background = colors[score];
            const label = document.getElementById('pw-strength-label');
            if (label) { label.textContent = labels[score]; label.style.color = colors[score]; }
        });
    }

    // ---- Election status auto-update badge color ----
    document.querySelectorAll('[data-enddate]').forEach(el => {
        const end = new Date(el.getAttribute('data-enddate'));
        if (end < new Date()) el.style.opacity = '0.6';
    });
});
