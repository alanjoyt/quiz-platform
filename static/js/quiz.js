document.addEventListener('DOMContentLoaded', () => {
    const timerElement = document.getElementById('quiz-timer');
    const quizForm = document.getElementById('quiz-form');
    const optionItems = document.querySelectorAll('.option-item');
    const warningModal = document.getElementById('warning-modal');
    const warningCountEl = document.getElementById('modal-warning-count');
    const closeWarningBtn = document.getElementById('close-warning-btn');
    
    if (!timerElement) return; // Exit if not on quiz screen
    
    // Apply no-copy class to body
    document.body.classList.add('no-copy');
    
    // State flags
    let isSubmitting = false;
    let pendingReloadOnReturn = false;
    let lastWarningTime = 0;
    let isPageActive = false;
    let blurTime = null;
    let remainingTime = 30;

    // Safe localStorage wrappers to prevent crashes on Brave or Private/Incognito windows
    const getStoredItem = (key) => {
        try { return localStorage.getItem(key); } catch (e) { return null; }
    };
    const setStoredItem = (key, value) => {
        try { localStorage.setItem(key, value); } catch (e) {}
    };
    const removeStoredItem = (key) => {
        try { localStorage.removeItem(key); } catch (e) {}
    };

    // Check warning state immediately on reload (handles browser memory discard restarts)
    syncWarningStateFromServer();

    // Enable anti-cheat triggers after a 1.5s grace period to prevent reload blur loops
    setTimeout(() => {
        isPageActive = true;
    }, 1500);

    // ==========================================
    // 1. Anti-Cheat: Disable Copy/Paste/Inspect
    // ==========================================
    
    // Disable right click
    document.addEventListener('contextmenu', e => e.preventDefault());
    
    // Disable copy, cut, paste
    document.addEventListener('copy', e => e.preventDefault());
    document.addEventListener('cut', e => e.preventDefault());
    document.addEventListener('paste', e => e.preventDefault());
    
    // Disable inspect and helper keys
    document.addEventListener('keydown', e => {
        if (e.key === 'F12') {
            e.preventDefault();
        }
        if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C')) {
            e.preventDefault();
        }
        if (e.ctrlKey && e.key === 'u') {
            e.preventDefault();
        }
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
        }
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
        }
    });

    // Intercept PrintScreen key
    document.addEventListener('keyup', e => {
        if (e.key === 'PrintScreen' || e.key === 'Snapshot') {
            navigator.clipboard.writeText('');
            handleCheatingDetection('screenshot');
            alert('Screenshots are strictly prohibited during the quiz.');
        }
    });

    // ==========================================
    // 2. Anti-Cheat: Window Blur Blackout & Warning
    // ==========================================
    
    // Blur content when focus is lost (preventing external side-viewing)
    window.addEventListener('blur', () => {
        if (!isSubmitting && isPageActive) {
            document.body.style.filter = 'blur(15px)';
            document.body.style.transition = 'filter 0.15s ease';
            if (blurTime === null) {
                blurTime = Date.now();
            }
            handleCheatingDetection('window_blur');
        }
    });
    
    window.addEventListener('focus', () => {
        document.body.style.filter = 'none';
        applyTimePenaltyOnFocus();
        syncWarningStateFromServer();
    });

    // Disable text selection, right click / long press context menu, copy, cut, and paste
    document.addEventListener('selectstart', e => e.preventDefault());
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('copy', e => e.preventDefault());
    document.addEventListener('cut', e => e.preventDefault());
    document.addEventListener('paste', e => e.preventDefault());

    // Split-Screen Detection for Mobile (Orientation-immune)
    let lastWidth = window.innerWidth;
    let lastHeight = window.innerHeight;
    let initialArea = lastWidth * lastHeight;

    window.addEventListener('resize', () => {
        if (isSubmitting) return;
        const currentWidth = window.innerWidth;
        const currentHeight = window.innerHeight;
        
        // Detect orientation change: if dimensions swapped (allowing small threshold), update base dimensions and don't warn
        if ((currentWidth === lastHeight && currentHeight === lastWidth) || 
            (Math.abs(currentWidth - lastHeight) < 20 && Math.abs(currentHeight - lastWidth) < 20)) {
            lastWidth = currentWidth;
            lastHeight = currentHeight;
            initialArea = currentWidth * currentHeight;
            return;
        }
        
        const currentArea = currentWidth * currentHeight;
        const areaRatio = currentArea / initialArea;
        
        if (areaRatio < 0.75) {
            handleCheatingDetection('split_screen_resize');
        }
    });

    // Timer logic
    remainingTime = parseInt(timerElement.dataset.remaining, 10) || 30;
    
    const countdown = setInterval(() => {
        let decrement = 1;
        if (document.hidden || !document.hasFocus()) {
            // Speed up timer by 1.5x in the background
            decrement = 1.5;
        }
        remainingTime -= decrement;
        timerElement.textContent = Math.max(0, Math.ceil(remainingTime));
        
        if (remainingTime <= 10) {
            timerElement.classList.add('quiz-timer-danger');
        }
        
        if (remainingTime <= 0) {
            clearInterval(countdown);
            submitTimedOut();
        }
    }, 1000);
    
    function submitTimedOut() {
        isSubmitting = true;
        const timeoutInput = document.createElement('input');
        timeoutInput.type = 'hidden';
        timeoutInput.name = 'option';
        timeoutInput.value = ''; 
        quizForm.appendChild(timeoutInput);
        quizForm.submit();
    }
    
    // Select option styling helper
    optionItems.forEach(item => {
        const radio = item.querySelector('input[type="radio"]');
        
        // Listen to change event on the radio to update the UI styles
        if (radio) {
            radio.addEventListener('change', () => {
                optionItems.forEach(opt => {
                    const optRadio = opt.querySelector('input[type="radio"]');
                    if (optRadio && optRadio.checked) {
                        opt.classList.add('selected');
                    } else {
                        opt.classList.remove('selected');
                    }
                });
            });
        }
        
        // Handle click on the container (e.g. padding area)
        item.addEventListener('click', (e) => {
            // Check if the click target is the input or inside/on a label
            let isLabelOrChild = false;
            let current = e.target;
            while (current && current !== item) {
                if (current.tagName === 'LABEL') {
                    isLabelOrChild = true;
                    break;
                }
                current = current.parentElement;
            }

            // If the user clicked something that natively checks the radio (like the label or radio itself), 
            // do not override it programmatically to prevent double-trigger/double-click bugs on some devices.
            if (e.target.tagName === 'INPUT' || isLabelOrChild) {
                return;
            }
            if (radio) {
                radio.checked = true;
                // Dispatch change event manually since setting .checked programmatically doesn't fire 'change'
                radio.dispatchEvent(new Event('change'));
            }
        });
    });
    
    // ==========================================
    // 3. Tab switching/Cheating warning log
    // ==========================================
    
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            document.body.style.filter = 'blur(15px)';
            document.body.style.transition = 'filter 0.15s ease';
            if (blurTime === null) {
                blurTime = Date.now();
            }
            if (isPageActive) {
                handleCheatingDetection('visibility_hidden');
            }
        } else {
            applyTimePenaltyOnFocus();
            syncWarningStateFromServer();
        }
    });
    
    function sendTelemetry(url, data) {
        const payload = JSON.stringify(data);
        try {
            if (navigator.sendBeacon) {
                const blob = new Blob([payload], { type: 'application/json' });
                if (navigator.sendBeacon(url, blob)) {
                    return;
                }
            }
        } catch (e) {
            console.error("sendBeacon error, using fallback:", e);
        }
        
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payload,
            keepalive: true
        }).catch(err => console.error("Telemetry failed:", err));
    }

    function applyTimePenaltyOnFocus() {
        if (blurTime !== null && isPageActive && !isSubmitting) {
            const now = Date.now();
            const awaySeconds = (now - blurTime) / 1000;
            blurTime = null;
            
            if (awaySeconds > 0.5) {
                // Penalty is 1.5x of the time spent in the background (meaning 0.5x extra deduction)
                const penaltySeconds = awaySeconds * 0.5;
                const totalDeduction = awaySeconds + penaltySeconds;
                
                remainingTime -= totalDeduction;
                timerElement.textContent = Math.max(0, Math.ceil(remainingTime));
                
                // Notify the server about the penalty to shift the start time backward
                const penaltyUrl = BASE_URI + 'quiz_warning.php';
                sendTelemetry(penaltyUrl, {
                    event_type: 'time_penalty',
                    penalty_seconds: penaltySeconds
                });
                
                if (remainingTime <= 0) {
                    submitTimedOut();
                }
            }
        }
    }
    
    function handleCheatingDetection(eventType) {
        if (isSubmitting) return;
        const now = Date.now();
        // Ignore duplicate reports within 3 seconds (cooldown)
        if (now - lastWarningTime < 3000) return;
        lastWarningTime = now;
        
        // 1. Store violation event type in localStorage safely
        setStoredItem('pending_warning', eventType);
        
        // 2. Log cheating attempt via beacon or fetch fallback
        const url = BASE_URI + 'quiz_warning.php';
        sendTelemetry(url, { event_type: eventType });
        
        // 3. If browser is active and window was blurred or took a screenshot, sync immediately
        if (eventType === 'screenshot' || eventType === 'window_blur') {
            syncWarningStateFromServer();
        }
    }
    
    function syncWarningStateFromServer() {
        const eventType = getStoredItem('pending_warning');
        if (!eventType) return;
        
        // Clear warning key to prevent loop triggers
        removeStoredItem('pending_warning');
        
        const url = BASE_URI + 'quiz_warning.php';
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ event_type: 'sync' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.disqualified) {
                    window.location.href = BASE_URI + 'quiz';
                } else {
                    // Display red warning message based on eventType
                    let reasonText = "ക്വിസ് നിയമങ്ങൾ ലംഘിച്ചിരിക്കുന്നു!";
                    if (eventType === 'visibility_hidden') {
                        reasonText = "ദയവായി ബ്രൗസർ ടാബുകൾ മാറാതിരിക്കുക!<br>(Please do not switch browser tabs!)";
                    } else if (eventType === 'window_blur') {
                        reasonText = "ദയവായി ക്വിസ് വിൻഡോയിൽ നിന്ന് പുറത്തുപോകാതിരിക്കുക!<br>(Please do not exit the quiz window!)";
                    } else if (eventType === 'screenshot') {
                        reasonText = "ക്വിസ് സമയത്ത് സ്ക്രീൻഷോട്ടുകൾ എടുക്കുന്നത് കർശനമായി നിരോധിച്ചിരിക്കുന്നു!<br>(Screenshots are strictly prohibited!)";
                    } else if (eventType === 'split_screen_resize') {
                        reasonText = "ദയവായി സ്പ്ലിറ്റ്-സ്ക്രീൻ ഉപയോഗിക്കാതിരിക്കുക!<br>(Please do not use split-screen mode!)";
                    }
                    
                    const reasonEl = document.getElementById('modal-warning-reason');
                    if (reasonEl) {
                        reasonEl.innerHTML = reasonText;
                        reasonEl.style.color = 'var(--error-color)';
                    }
                    
                    if (warningCountEl) {
                        warningCountEl.textContent = data.warnings_count;
                    }
                    
                    const warningsIndicator = document.getElementById('quiz-warnings-indicator');
                    const warningsCountDisplay = document.getElementById('quiz-warnings-count-display');
                    if (warningsCountDisplay) {
                        warningsCountDisplay.textContent = data.warnings_count;
                    }
                    if (warningsIndicator) {
                        warningsIndicator.style.display = 'block';
                    }
                    if (warningModal) {
                        warningModal.style.display = 'flex';
                    }
                }
            }
        })
        .catch(err => console.error("Error syncing warning state:", err));
    }
    
    if (closeWarningBtn) {
        closeWarningBtn.addEventListener('click', () => {
            if (warningModal) warningModal.style.display = 'none';
        });
    }

    // ==========================================
    // 4. Anti-Cheat: Disable Back Button & Reload
    // ==========================================
    
    // Prevent Back Button
    history.pushState(null, null, document.URL);
    window.addEventListener('popstate', () => {
        history.pushState(null, null, document.URL);
        alert('Navigation is disabled during the active quiz session.');
    });

    // Set flag and handle submit styling when form is submitted, preventing rapid double-clicks
    if (quizForm) {
        quizForm.addEventListener('submit', (e) => {
            // 1. Validation: Ensure an option is selected before submission
            const selectedOption = quizForm.querySelector('input[name="option"]:checked');
            if (!selectedOption) {
                e.preventDefault();
                alert('ദയവായി ഒരു ഉത്തരം തിരഞ്ഞെടുക്കുക! (Please select an option before submitting!)');
                return;
            }

            if (isSubmitting) {
                e.preventDefault();
                return;
            }
            isSubmitting = true;
            const submitBtn = quizForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                // Delay disabling button to allow Safari and other browsers to submit the form data properly
                setTimeout(() => {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Submitting...';
                    submitBtn.style.opacity = '0.6';
                    submitBtn.style.cursor = 'not-allowed';
                }, 0);
            }
        });
    }
});
