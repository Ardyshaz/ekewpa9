// js/form-dynamic-assets.js

document.addEventListener('DOMContentLoaded', function() {
    const addAssetButton = document.getElementById('addAsset');
    const assetsTableBody = document.getElementById('assetsTableBody');
    const applicationForm = document.getElementById('applicationForm');
    const messageArea = document.getElementById('message-area'); // Assuming a message area exists

    // Function to create a new asset row
    function createAssetRow(asset = {}) {
        if (!assetsTableBody) { // Defensive check: Ensure assetsTableBody exists
            console.error("assetsTableBody is null inside createAssetRow. Cannot add row.");
            return;
        }
        const rowCount = assetsTableBody.querySelectorAll('tr').length + 1;
        const newRow = document.createElement('tr');
        newRow.className = 'border-b border-gray-200';
        newRow.innerHTML = `
            <td class="py-2 px-4 text-sm text-gray-700">${rowCount}.</td>
            <td class="py-2 px-4">
                <input type="text" name="assets[${rowCount - 1}][serial_number]" value="${asset.serial_number || ''}" placeholder="No. Siri Pendaftaran" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </td>
            <td class="py-2 px-4">
                <input type="text" name="assets[${rowCount - 1}][description]" value="${asset.description || ''}" placeholder="Keterangan Aset" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </td>
            <td class="py-2 px-4">
                <input type="date" name="assets[${rowCount - 1}][loan_date]" value="${asset.loan_date || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </td>
            <td class="py-2 px-4">
                <input type="date" name="assets[${rowCount - 1}][expected_return_date]" value="${asset.expected_return_date || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </td>
            <td class="py-2 px-4 text-center">
                <button type="button" class="remove-asset-button bg-red-500 hover:bg-red-600 text-white text-xs py-1 px-2 rounded-md transition-colors">Buang</button>
            </td>
        `;
        assetsTableBody.appendChild(newRow);
        setupRemoveButton(newRow);
    }

    // Function to set up remove button listener for a given row
    function setupRemoveButton(row) {
        const removeButton = row.querySelector('.remove-asset-button');
        if (removeButton) {
            removeButton.addEventListener('click', function() {
                row.remove();
                updateRowNumbers(); // Re-number rows after removal
            });
        }
    }

    // Function to update row numbers after an asset is removed
    function updateRowNumbers() {
        const rows = assetsTableBody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            row.querySelector('td:first-child').textContent = `${index + 1}.`;
            // Also update the name attributes for correct indexing
            row.querySelectorAll('input').forEach(input => {
                const nameAttr = input.getAttribute('name');
                if (nameAttr) {
                    input.setAttribute('name', nameAttr.replace(/assets\[\d+\]/, `assets[${index}]`));
                }
            });
        });
    }

    // Add event listener for the "Tambah Aset" button
    if (addAssetButton) {
        addAssetButton.addEventListener('click', function() {
            createAssetRow();
        });
    }

    // Handle form submission for process.php
    if (applicationForm) {
        applicationForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent default form submission

            const formData = new FormData(applicationForm);
            // Append dynamic asset data
            const assetsData = [];
            if (assetsTableBody) { // Ensure assetsTableBody exists before querying its children
                assetsTableBody.querySelectorAll('tr').forEach(row => {
                    const asset = {};
                    row.querySelectorAll('input').forEach(input => {
                        const name = input.name.match(/\[(\w+)\]$/);
                        if (name && name[1]) {
                            asset[name[1]] = input.value;
                        }
                    });
                    assetsData.push(asset);
                });
            }
            formData.append('assets_json', JSON.stringify(assetsData)); // Send assets as JSON string

            // Disable submit button and show loading state
            const submitButton = applicationForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Menghantar...';
            submitButton.classList.add('opacity-75', 'cursor-not-allowed');

            if (messageArea) {
                messageArea.innerHTML = ''; // Clear previous messages
            }

            fetch('process.php', {
                method: 'POST',
                // TAMBAH HEADERS UNTUK MENUNJUKKAN INI ADALAH PERMINTAAN AJAX
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                // Check if response is JSON, if not, parse as text for debugging
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        console.error("Server responded with non-JSON content:", text);
                        throw new Error("Server response was not JSON. Check server logs.");
                    });
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    displayMessage(data.message, 'success');
                    applicationForm.reset(); // Clear the main form fields
                    if (assetsTableBody) { // Ensure assetsTableBody exists before clearing
                        assetsTableBody.innerHTML = ''; // Clear dynamic asset rows
                        createAssetRow(); // Add one empty row back
                    }

                    // Optionally, redirect to view application page or dashboard
                    if (data.application_id) {
                        setTimeout(() => {
                            window.location.href = 'view_application.php?id=' + data.application_id;
                        }, 2000); // Redirect after 2 seconds
                    }

                } else {
                    displayMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Ralat semasa menghantar permohonan:', error);
                displayMessage('Terdapat ralat rangkaian atau pelayan: ' + error.message, 'error');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = 'Hantar Permohonan';
                submitButton.classList.remove('opacity-75', 'cursor-not-allowed');
            });
        });
    }

    // Helper function to display messages (reused from admin-actions.js)
    function displayMessage(msg, type) {
        if (!messageArea) return;
        const html = `
            <div class="p-4 mb-6 rounded-lg ${type === 'success' ? 'bg-green-100 text-green-700 border-green-400' : 'bg-red-100 text-red-700 border-red-400'} border shadow-md flex items-center space-x-3">
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
        messageArea.innerHTML = html;
        // Optionally, hide message after a few seconds
        setTimeout(() => {
            if (messageArea.innerHTML) messageArea.innerHTML = '';
        }, 5000); // Message disappears after 5 seconds
    }

    // Add initial asset row when the page loads
    if (assetsTableBody) {
        createAssetRow();
    } else {
        console.error("Element with ID 'assetsTableBody' not found. Dynamic asset addition will not work.");
    }
});
