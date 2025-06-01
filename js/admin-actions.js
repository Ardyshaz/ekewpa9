// js/admin-actions.js

document.addEventListener('DOMContentLoaded', function() {
    // --- Application Status Update Elements ---
    const statusUpdateForm = document.getElementById('statusUpdateForm');
    const applicationStatusSpan = document.getElementById('application-status');
    const messageArea = document.getElementById('message-area');

    // --- Signature Modal Elements ---
    const signatureModal = document.getElementById('signatureModal');
    const modalSignatureCanvas = document.getElementById('modalSignatureCanvas');
    const modalClearSignatureBtn = document.getElementById('modalClearSignature');
    const modalSaveSignatureBtn = document.getElementById('modalSaveSignature');
    const closeModalBtn = document.getElementById('closeModal');
    const modalApplicationIdInput = document.getElementById('modalApplicationId');
    const modalSignatureRoleInput = document.getElementById('modalSignatureRole');
    const modalRoleNameSpan = document.getElementById('modalRoleName');
    const modalSignerNameInput = document.getElementById('modalSignerName');
    const modalSignerPositionInput = document.getElementById('modalSignerPosition');
    let modalCtx; // Context for the modal signature canvas

    // --- Return Asset Modal Elements ---
    const returnAssetModal = document.getElementById('returnAssetModal');
    const closeReturnModalBtn = document.getElementById('closeReturnModal');
    const cancelReturnBtn = document.getElementById('cancelReturn');
    const confirmReturnBtn = document.getElementById('confirmReturn');
    const returnAssetIdInput = document.getElementById('returnAssetId');
    const returnApplicationIdInput = document.getElementById('returnApplicationId');
    const actualReturnDateInput = document.getElementById('actualReturnDate');
    const returnNotesInput = document.getElementById('returnNotes');

    // --- Signature Pad Logic (reused from signature-pad.js but for modularity, kept here) ---
    let drawing = false;

    // Helper function to resize canvas
    function resizeCanvas(canvas, context) {
        if (!canvas || !context) return;
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        context.lineWidth = 2;
        context.lineCap = 'round';
        context.strokeStyle = '#000';
    }

    // Helper function to add drawing listeners
    function addDrawingListeners(canvas, context) {
        if (!canvas || !context) return;
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        canvas.addEventListener('mousemove', draw);

        // For touch devices
        canvas.addEventListener('touchstart', startDrawing);
        canvas.addEventListener('touchend', stopDrawing);
        canvas.addEventListener('touchcancel', stopDrawing);
        canvas.addEventListener('touchmove', draw);

        function startDrawing(e) {
            drawing = true;
            context.beginPath();
            const pos = getMousePos(canvas, e);
            context.moveTo(pos.x, pos.y);
            e.preventDefault();
        }

        function stopDrawing() {
            drawing = false;
        }

        function draw(e) {
            if (!drawing) return;
            const pos = getMousePos(canvas, e);
            context.lineTo(pos.x, pos.y);
            context.stroke();
            e.preventDefault();
        }

        function getMousePos(canvas, event) {
            const rect = canvas.getBoundingClientRect();
            let clientX, clientY;

            if (event.touches && event.touches.length > 0) {
                clientX = event.touches[0].clientX;
                clientY = event.touches[0].clientY;
            } else {
                clientX = event.clientX;
                clientY = event.clientY;
            }

            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }
    }

    // Helper function to clear signature
    function clearSignature(canvas, context) {
        if (!canvas || !context) return;
        context.clearRect(0, 0, canvas.width, canvas.height);
    }

    // --- Application Status Update Logic ---
    if (statusUpdateForm) {
        statusUpdateForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(statusUpdateForm);
            const applicationId = formData.get('application_id');

            // MENDAPATKAN NILAI status_action DENGAN BETUL DARI BUTANG YANG DIKLIK
            let statusAction = '';
            const clickedButton = event.submitter;
            if (clickedButton && clickedButton.name === 'status_action') {
                statusAction = clickedButton.value;
            }

            if (!applicationId || !statusAction) {
                console.error('Missing application ID or status action. applicationId:', applicationId, 'statusAction:', statusAction);
                displayMessage('Ralat: ID permohonan atau tindakan status tidak lengkap.', 'error');
                return;
            }

            const submitButtons = statusUpdateForm.querySelectorAll('button[type="submit"]');
            submitButtons.forEach(button => {
                button.disabled = true;
                button.textContent = 'Mengemas kini...';
                button.classList.add('opacity-75', 'cursor-not-allowed');
            });

            if (messageArea) {
                messageArea.innerHTML = '';
            }

            fetch('view_application.php?id=' + applicationId, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Important: identifies as AJAX request
                },
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    // If response is not JSON, read as text and log it
                    return response.text().then(text => {
                        console.error("Server responded with non-JSON content:", text);
                        throw new Error("Server response was not JSON. Check server logs for full output.");
                    });
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    if (applicationStatusSpan && data.new_status_text && data.new_status_class) {
                        applicationStatusSpan.textContent = data.new_status_text;
                        // Remove existing background and text color classes before adding new ones
                        applicationStatusSpan.className = applicationStatusSpan.className.split(' ').filter(c => !c.startsWith('bg-') && !c.startsWith('text-')).join(' ');
                        applicationStatusSpan.classList.add(...data.new_status_class.split(' '));
                    }

                    displayMessage(data.message, 'success');

                    const currentStatusValue = data.new_status_value;
                    const statusParagraph = statusUpdateForm.querySelector('p.text-lg.text-gray-700.font-medium.text-center.self-center');

                    submitButtons.forEach(button => {
                         if (button.name === 'status_action') { // Ensure we only modify action buttons
                            if (currentStatusValue === 'approved') {
                                if (button.value === 'approved') {
                                    button.style.display = 'none';
                                } else if (button.value === 'rejected') {
                                    button.style.display = '';
                                    button.textContent = 'Tolak Permohonan (Ubah Status)';
                                }
                            } else if (currentStatusValue === 'rejected') {
                                if (button.value === 'rejected') {
                                    button.style.display = 'none';
                                } else if (button.value === 'approved') {
                                    button.style.display = '';
                                    button.textContent = 'Luluskan Permohonan (Ubah Status)';
                                }
                            } else { // For 'submitted' or 'draft' (if applicable)
                                if (button.value === 'approved') {
                                    button.style.display = '';
                                    button.textContent = 'Luluskan Permohonan';
                                } else if (button.value === 'rejected') {
                                    button.style.display = '';
                                    button.textContent = 'Tolak Permohonan';
                                }
                            }
                        }
                    });

                    if (statusParagraph) {
                        if (currentStatusValue === 'approved') {
                            statusParagraph.innerHTML = `Status permohonan ini adalah <span class="font-bold text-green-600">Diluluskan</span>.`;
                            statusParagraph.style.display = '';
                        } else if (currentStatusValue === 'rejected') {
                            statusParagraph.innerHTML = `Status permohonan ini adalah <span class="font-bold text-red-600">Ditolak</span>.`;
                            statusParagraph.style.display = '';
                        } else {
                            statusParagraph.style.display = 'none';
                        }
                    }

                    // If application is approved, enable return buttons for assets
                    if (currentStatusValue === 'approved') {
                        document.querySelectorAll('.return-asset-button').forEach(btn => {
                            const assetId = btn.dataset.assetId;
                            const assetStatusSpan = document.getElementById(`asset-status-${assetId}`);
                            if (assetStatusSpan && assetStatusSpan.textContent.trim() !== 'Dipulangkan') {
                                btn.style.display = ''; // Show button
                            }
                        });
                    }

                } else {
                    displayMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Ralat semasa mengemas kini status:', error);
                displayMessage('Terdapat ralat rangkaian atau pelayan. Sila cuba lagi. Butiran: ' + error.message, 'error');
            })
            .finally(() => {
                submitButtons.forEach(button => {
                    button.disabled = false;
                    button.classList.remove('opacity-75', 'cursor-not-allowed');
                });
            });
        });
    }

    // --- Admin Signature Modal Logic ---
    if (modalSignatureCanvas) {
        modalCtx = modalSignatureCanvas.getContext('2d');
        resizeCanvas(modalSignatureCanvas, modalCtx);
        addDrawingListeners(modalSignatureCanvas, modalCtx);

        if(modalClearSignatureBtn) {
            modalClearSignatureBtn.addEventListener('click', function() {
                clearSignature(modalSignatureCanvas, modalCtx);
            });
        }

        if(closeModalBtn) {
            closeModalBtn.addEventListener('click', function() {
                if(signatureModal) signatureModal.classList.add('hidden');
                clearSignature(modalSignatureCanvas, modalCtx);
            });
        }

        if(modalSaveSignatureBtn) {
            modalSaveSignatureBtn.addEventListener('click', function() {
                saveModalSignature();
            });
        }

        document.querySelectorAll('.sign-button').forEach(button => {
            button.addEventListener('click', function() {
                const role = this.dataset.role;
                const appId = this.dataset.appId;

                if(modalApplicationIdInput) modalApplicationIdInput.value = appId;
                if(modalSignatureRoleInput) modalSignatureRoleInput.value = role;
                if(modalRoleNameSpan) modalRoleNameSpan.textContent = getRoleDisplayName(role);

                if(modalSignerNameInput) modalSignerNameInput.value = ''; // Clear previous entries
                if(modalSignerPositionInput) modalSignerPositionInput.value = ''; // Clear previous entries

                clearSignature(modalSignatureCanvas, modalCtx);
                if(signatureModal) signatureModal.classList.remove('hidden');
            });
        });
    }

    function saveModalSignature() {
        if (!modalSignatureCanvas || !modalApplicationIdInput || !modalSignatureRoleInput || !modalSignerNameInput || !modalSignerPositionInput) return;

        const signatureData = modalSignatureCanvas.toDataURL();
        const appId = modalApplicationIdInput.value;
        const role = modalSignatureRoleInput.value;
        const signerName = modalSignerNameInput.value;
        const signerPosition = modalSignerPositionInput.value;

        // Check if signature pad is empty
        const isEmpty = !modalSignatureCanvas.getContext('2d')
            .getImageData(0, 0, modalSignatureCanvas.width, modalSignatureCanvas.height)
            .data.some(channel => channel !== 0);


        if (isEmpty || !appId || !role || !signerName || !signerPosition) {
            displayMessage('Sila lengkapkan semua maklumat dan berikan tandatangan.', 'error');
            return;
        }

        fetch('save_signature.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                application_id: appId,
                role: role,
                name: signerName,
                position: signerPosition,
                signature_data: signatureData
            })
        })
        .then(response => {
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return response.json();
            } else {
                return response.text().then(text => {
                    console.error("Server responded with non-JSON content:", text);
                    throw new Error("Server response was not JSON for signature saving.");
                });
            }
        })
        .then(data => {
            if (data.status === 'success') {
                displayMessage('Tandatangan berjaya disimpan!', 'success');
                if(signatureModal) signatureModal.classList.add('hidden');
                window.location.reload(); // Reload to show new signature
            } else {
                displayMessage('Ralat menyimpan tandatangan: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error saving signature:', error);
            displayMessage('Ralat rangkaian semasa menyimpan tandatangan. Butiran: ' + error.message, 'error');
        });
    }

    function getRoleDisplayName(role) {
        switch(role) {
            case 'approver': return 'Pelulus';
            case 'returner': return 'Pemulang';
            case 'receiver': return 'Penerima';
            default: return role.charAt(0).toUpperCase() + role.slice(1); // Capitalize first letter as a fallback
        }
    }

    // --- Return Asset Modal Logic ---
    if (returnAssetModal) {
        document.querySelectorAll('.return-asset-button').forEach(button => {
            button.addEventListener('click', function() {
                const assetId = this.dataset.assetId;
                const applicationId = this.dataset.applicationId;

                if(returnAssetIdInput) returnAssetIdInput.value = assetId;
                if(returnApplicationIdInput) returnApplicationIdInput.value = applicationId;
                if(actualReturnDateInput) actualReturnDateInput.value = new Date().toISOString().slice(0, 10); // Set today's date
                if(returnNotesInput) returnNotesInput.value = ''; // Clear notes

                returnAssetModal.classList.remove('hidden');
            });
        });

        if(closeReturnModalBtn) {
            closeReturnModalBtn.addEventListener('click', function() {
                returnAssetModal.classList.add('hidden');
            });
        }

        if(cancelReturnBtn) {
            cancelReturnBtn.addEventListener('click', function() {
                returnAssetModal.classList.add('hidden');
            });
        }

        if(confirmReturnBtn) {
            confirmReturnBtn.addEventListener('click', function() {
                if (!returnAssetIdInput || !returnApplicationIdInput || !actualReturnDateInput || !returnNotesInput) return;

                const assetId = returnAssetIdInput.value;
                const applicationId = returnApplicationIdInput.value;
                const actualReturnDate = actualReturnDateInput.value;
                const returnNotes = returnNotesInput.value;

                if (!actualReturnDate) {
                    displayMessage('Sila masukkan Tarikh Dipulangkan Sebenar.', 'error');
                    return;
                }

                // Disable button and show loading
                confirmReturnBtn.disabled = true;
                confirmReturnBtn.textContent = 'Memproses...';
                confirmReturnBtn.classList.add('opacity-75', 'cursor-not-allowed');

                fetch('view_application.php?id=' + applicationId, { // POST to view_application.php
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded', // form-urlencoded for this specific action
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        asset_id_to_return: assetId,
                        actual_return_date: actualReturnDate,
                        return_notes: returnNotes
                    }).toString()
                })
                .then(response => {
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        return response.text().then(text => {
                            console.error("Server responded with non-JSON content (asset return):", text);
                            throw new Error("Server response was not JSON for asset return.");
                        });
                    }
                })
                .then(data => {
                    if (data.status === 'success') {
                        displayMessage(data.message, 'success');
                        if(returnAssetModal) returnAssetModal.classList.add('hidden');

                        // Update the specific asset row on the page
                        const assetStatusSpan = document.getElementById(`asset-status-${data.asset_id}`);
                        const actualReturnDateTd = document.getElementById(`actual-return-date-${data.asset_id}`);
                        const assetNotesTd = document.getElementById(`asset-notes-${data.asset_id}`);
                        const returnButton = document.querySelector(`.return-asset-button[data-asset-id="${data.asset_id}"]`);

                        if (assetStatusSpan) {
                            assetStatusSpan.textContent = 'Dipulangkan';
                            assetStatusSpan.className = assetStatusSpan.className.split(' ').filter(c => !c.startsWith('bg-') && !c.startsWith('text-')).join(' ');
                            assetStatusSpan.classList.add('bg-blue-100', 'text-blue-800');
                        }
                        if (actualReturnDateTd) {
                            actualReturnDateTd.textContent = data.actual_return_date_formatted || data.actual_return_date; // Use formatted if available
                        }
                        if (assetNotesTd) {
                            assetNotesTd.textContent = data.notes || 'Tiada';
                        }
                        if (returnButton) {
                            returnButton.remove(); // Remove the button after asset is returned
                        }

                        // KEMAS KINI STATUS PERMOHONAN UTAMA JIKA SEMUA ASET TELAH DIPULANGKAN
                        if (data.application_status_changed_to_completed) {
                            if (applicationStatusSpan) {
                                applicationStatusSpan.textContent = 'Lengkap';
                                applicationStatusSpan.className = applicationStatusSpan.className.split(' ').filter(c => !c.startsWith('bg-') && !c.startsWith('text-')).join(' ');
                                applicationStatusSpan.classList.add('bg-gray-600', 'text-white'); // Contoh kelas untuk status lengkap
                            }
                            // Anda mungkin ingin menyembunyikan borang tindakan status jika permohonan lengkap
                            if (statusUpdateForm) {
                                statusUpdateForm.style.display = 'none';
                            }
                        }
                        // Optionally reload if there are other dependent changes
                        // window.location.reload();

                    } else {
                        displayMessage('Ralat pemulangan aset: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error returning asset:', error);
                    displayMessage('Ralat rangkaian semasa memulangkan aset. Butiran: ' + error.message, 'error');
                })
                .finally(() => {
                    if(confirmReturnBtn) {
                        confirmReturnBtn.disabled = false;
                        confirmReturnBtn.textContent = 'Sahkan Pemulangan';
                        confirmReturnBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                    }
                });
            });
        }
    }

    // Helper function to display messages
    function displayMessage(msg, type) {
        if (!messageArea) return;
        const messageDivId = `temp-message-${Date.now()}`;
        const html = `
            <div id="${messageDivId}" class="p-4 mb-6 rounded-lg ${type === 'success' ? 'bg-green-100 text-green-700 border-green-400' : 'bg-red-100 text-red-700 border-red-400'} border shadow-md flex items-center space-x-3">
                ${type === 'success' ? `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                ` : `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                `}
                <p class="font-medium">${msg}</p>
            </div>
        `;
        messageArea.innerHTML = html; // Replace previous message
        // Optionally, hide message after a few seconds
        setTimeout(() => {
            const currentMessage = document.getElementById(messageDivId);
            if (currentMessage) currentMessage.remove();
        }, 5000); // Message disappears after 5 seconds
    }


    // Adjust canvas size on window resize
    window.addEventListener('resize', function() {
        if (modalSignatureCanvas && modalCtx) resizeCanvas(modalSignatureCanvas, modalCtx);
        // If you have other canvases that need resizing, add them here
    });
});
