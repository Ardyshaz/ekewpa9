// js/signature-pad.js

document.addEventListener('DOMContentLoaded', function() {
    // For Applicant Signature on process.php
    const signatureCanvas = document.getElementById('signatureCanvas');
    const clearSignatureBtn = document.getElementById('clearSignature');
    const applicantSignatureDataInput = document.getElementById('applicant_signature_data');

    // For Admin Signature Modal on view_application.php
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

    let ctx, modalCtx;
    let drawing = false;

    // Initialize signature pad for applicant form
    if (signatureCanvas) {
        ctx = signatureCanvas.getContext('2d');
        resizeCanvas(signatureCanvas, ctx); // Ensure canvas is sized correctly
        addSignaturePadListeners(signatureCanvas, ctx, applicantSignatureDataInput);

        clearSignatureBtn.addEventListener('click', function() {
            clearSignature(signatureCanvas, ctx, applicantSignatureDataInput);
        });

        // Save signature data when form is submitted
        const applicationForm = document.getElementById('applicationForm');
        if (applicationForm) {
            applicationForm.addEventListener('submit', function() {
                if (ctx.getImageData(0, 0, signatureCanvas.width, signatureCanvas.height).data.some(channel => channel !== 0)) {
                    // Only save if something is drawn (not entirely transparent)
                    applicantSignatureDataInput.value = signatureCanvas.toDataURL();
                } else {
                    applicantSignatureDataInput.value = ''; // Clear if no signature
                }
            });
        }
    }

    // Initialize signature pad for admin modal
    if (modalSignatureCanvas) {
        modalCtx = modalSignatureCanvas.getContext('2d');
        resizeCanvas(modalSignatureCanvas, modalCtx);
        addSignaturePadListeners(modalSignatureCanvas, modalCtx); // No direct input for modal, handled on save

        modalClearSignatureBtn.addEventListener('click', function() {
            clearSignature(modalSignatureCanvas, modalCtx);
        });

        closeModalBtn.addEventListener('click', function() {
            signatureModal.classList.add('hidden');
            clearSignature(modalSignatureCanvas, modalCtx); // Clear canvas on close
        });

        modalSaveSignatureBtn.addEventListener('click', function() {
            saveModalSignature();
        });

        // Event listeners for admin sign buttons
        document.querySelectorAll('.sign-button').forEach(button => {
            button.addEventListener('click', function() {
                const role = this.dataset.role;
                const appId = this.dataset.appId;

                modalApplicationIdInput.value = appId;
                modalSignatureRoleInput.value = role;
                modalRoleNameSpan.textContent = getRoleDisplayName(role); // Set role name in modal title

                // Pre-fill name and position if available (e.g., from logged-in user, or fetch from DB)
                // For this example, we'll leave them blank or add dummy data
                modalSignerNameInput.value = ''; // You might fetch this from a user session
                modalSignerPositionInput.value = ''; // You might fetch this from a user session

                clearSignature(modalSignatureCanvas, modalCtx); // Clear canvas for new signature
                signatureModal.classList.remove('hidden');
            });
        });
    }

    // Helper function to resize canvas
    function resizeCanvas(canvas, context) {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        context.lineWidth = 2;
        context.lineCap = 'round';
        context.strokeStyle = '#000';
    }

    // Helper function to add drawing listeners
    function addSignaturePadListeners(canvas, context, outputInput = null) {
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
            e.preventDefault(); // Prevent scrolling on touch devices
        }

        function stopDrawing() {
            drawing = false;
        }

        function draw(e) {
            if (!drawing) return;
            const pos = getMousePos(canvas, e);
            context.lineTo(pos.x, pos.y);
            context.stroke();
            e.preventDefault(); // Prevent scrolling on touch devices
        }

        function getMousePos(canvas, event) {
            const rect = canvas.getBoundingClientRect();
            let clientX, clientY;

            if (event.touches) {
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

        // Update hidden input if provided (for applicant form)
        if (outputInput) {
            canvas.addEventListener('mouseup', () => {
                if (context.getImageData(0, 0, canvas.width, canvas.height).data.some(channel => channel !== 0)) {
                    outputInput.value = canvas.toDataURL();
                } else {
                    outputInput.value = '';
                }
            });
            canvas.addEventListener('touchend', () => {
                if (context.getImageData(0, 0, canvas.width, canvas.height).data.some(channel => channel !== 0)) {
                    outputInput.value = canvas.toDataURL();
                } else {
                    outputInput.value = '';
                }
            });
        }
    }

    // Helper function to clear signature
    function clearSignature(canvas, context, outputInput = null) {
        context.clearRect(0, 0, canvas.width, canvas.height);
        if (outputInput) {
            outputInput.value = '';
        }
    }

    // Function to save signature from modal
    function saveModalSignature() {
        const signatureData = modalSignatureCanvas.toDataURL();
        const appId = modalApplicationIdInput.value;
        const role = modalSignatureRoleInput.value;
        const signerName = modalSignerNameInput.value;
        const signerPosition = modalSignerPositionInput.value;

        if (!signatureData || !appId || !role || !signerName || !signerPosition) {
            alert('Sila lengkapkan semua maklumat dan berikan tandatangan.'); // Use custom modal in real app
            return;
        }

        // Send signature data to server via AJAX
        fetch('save_signature.php', { // You will create this file
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                application_id: appId,
                role: role,
                name: signerName,
                position: signerPosition,
                signature_data: signatureData
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Tandatangan berjaya disimpan!'); // Use custom modal
                signatureModal.classList.add('hidden');
                // Reload the view_application page to show the new signature
                window.location.reload();
            } else {
                alert('Ralat menyimpan tandatangan: ' + data.message); // Use custom modal
            }
        })
        .catch(error => {
            console.error('Error saving signature:', error);
            alert('Ralat rangkaian semasa menyimpan tandatangan.'); // Use custom modal
        });
    }

    function getRoleDisplayName(role) {
        switch(role) {
            case 'approver': return 'Pelulus';
            case 'returner': return 'Pemulang';
            case 'receiver': return 'Penerima';
            default: return role;
        }
    }

    // Adjust canvas size on window resize
    window.addEventListener('resize', function() {
        if (signatureCanvas) resizeCanvas(signatureCanvas, ctx);
        if (modalSignatureCanvas) resizeCanvas(modalSignatureCanvas, modalCtx);
    });
});
