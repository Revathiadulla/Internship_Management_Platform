
    // Add HOD approval button handler
    document.querySelectorAll('.send-hod-approval-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const appId = this.dataset.appId;
            const formData = new FormData();
            formData.append('application_id', appId);
            try {
                const response = await fetch('send_hod_approval.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast('success', 'Success', result.message);
                    // Optionally refresh to show updated status
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error', result.message);
                }
            } catch (e) {
                showToast('error', 'Error', 'Failed to send HOD approval request');
            }
        });
    });

    // Status update handler
    document.querySelectorAll('.status-update-select').forEach(select => {
      select.addEventListener('change', async function() {
        const appId = this.dataset.appId;
        const newStatus = this.value;
        
        if (!newStatus) return;
        
        // Confirm action
        if (!confirm(`Update application status to "${newStatus}"?`)) {
          this.value = '';
          return;
        }
        
        try {
          const formData = new FormData();
          formData.append('application_id', appId);
          formData.append('new_status', newStatus);
          formData.append('notes', 'Status updated by HR');
          
          const response = await fetch('update_application_status.php', {
            method: 'POST',
            body: formData
          });
          
          const result = await response.json();
          
          if (result.success) {
            showToast('success', 'Success', result.message);
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast('error', 'Error', result.message);
            this.value = '';
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to update status');
          this.value = '';
        }
      });
    });

    document.querySelectorAll('.verification-update-select').forEach(select => {
      select.addEventListener('change', async function() {
        const appId = this.dataset.appId;
        const newVerification = this.value;
        if (!newVerification) return;

        if (!confirm(`Update verification status to "${newVerification}"?`)) {
          this.value = '';
          return;
        }

        try {
          const formData = new FormData();
          formData.append('application_id', appId);
          formData.append('verification_status', newVerification);

          const response = await fetch('update_verification_status.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();
          if (result.success) {
            showToast('success', 'Success', result.message);
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast('error', 'Error', result.message);
            this.value = '';
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to update verification status');
          this.value = '';
        }
      });
    });

    const bulkSelectAllTop = document.getElementById('bulk-select-all-top');
    const bulkSelectAll = document.getElementById('bulk-select-all');
    const bulkRows = Array.from(document.querySelectorAll('.bulk-select-row'));
    const bulkActionSelect = document.getElementById('bulk-action-select');
    const bulkApplyButton = document.getElementById('bulk-action-apply');
    const bulkSelectedCount = document.getElementById('bulk-selected-count');

    function updateBulkSelectionDisplay() {
      const selectableRows = bulkRows.filter(row => !row.disabled);
      const selectedRows = selectableRows.filter(row => row.checked);
      const count = selectedRows.length;
      const enabled = count > 0 && bulkActionSelect.value !== '';
      bulkApplyButton.disabled = !enabled;
      bulkSelectedCount.textContent = count > 0 ? `${count} selected` : '';
      bulkSelectedCount.classList.toggle('hidden', count === 0);
      const allChecked = selectableRows.length > 0 && selectedRows.length === selectableRows.length;
      if (bulkSelectAll) bulkSelectAll.checked = allChecked;
      if (bulkSelectAllTop) bulkSelectAllTop.checked = allChecked;
    }

    function setBulkSelection(checked) {
      bulkRows.forEach(row => {
        if (!row.disabled) {
          row.checked = checked;
        }
      });
      updateBulkSelectionDisplay();
    }

    if (bulkSelectAll) {
      bulkSelectAll.addEventListener('change', function() {
        setBulkSelection(this.checked);
      });
    }

    if (bulkSelectAllTop) {
      bulkSelectAllTop.addEventListener('change', function() {
        setBulkSelection(this.checked);
      });
    }

    bulkRows.forEach(row => {
      row.addEventListener('change', updateBulkSelectionDisplay);
    });

    if (bulkActionSelect) {
      bulkActionSelect.addEventListener('change', updateBulkSelectionDisplay);
    }
    updateBulkSelectionDisplay();

    if (bulkApplyButton) {
      bulkApplyButton.addEventListener('click', async function() {
        const selectedIds = bulkRows.filter(row => !row.disabled && row.checked).map(row => row.dataset.appId).filter(Boolean);
        const selectedRows = bulkRows.filter(row => !row.disabled && row.checked);
        const action = bulkActionSelect.value;

        if (selectedIds.length === 0) {
          alert('Please select at least one student.');
          showToast('error', 'No Selection', 'Please select at least one student.');
          return;
        }
        if (!action) {
          alert('Please select an action.');
          showToast('error', 'Choose Action', 'Please select an action.');
          return;
        }

        if (action === 'send_exam_link' || action === 'send_exam_mail') {
          openBulkExamComposeModal(selectedIds, selectedRows);
          return;
        }

        const actionLabelMap = {
          move_to_hod_approved: 'Move to HOD Approved',
          select_candidate: 'Select',
          reject: 'Reject',
          verification_pending: 'Verification Pending',
          verify: 'Verify',
          verification_rejected: 'Verification Rejected',
          delete: 'Delete',
          archive: 'Archive'
        };
        const confirmText = `Are you sure you want to perform bulk action "${actionLabelMap[action] || action}" on ${selectedIds.length} application(s)?`;
        if (!confirm(confirmText)) {
          return;
        }

        try {
          const formData = new FormData();
          selectedIds.forEach(id => formData.append('application_ids[]', id));
          formData.append('action', action);

          const response = await fetch('bulk_update_applications.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();
          if (result.success) {
            showToast('success', 'Bulk update complete', result.message || 'Applications updated successfully.');
            setTimeout(() => location.reload(), 1300);
          } else {
            showToast('error', 'Bulk update failed', result.message || 'Unable to apply bulk action.');
          }
        } catch (error) {
          showToast('error', 'Error', 'Bulk action request failed.');
          console.error(error);
        }
      });
    }
    
    function showToast(type, title, message) {
      const toast = document.getElementById('toast');
      const toastIcon = document.getElementById('toast-icon');
      const toastIconContainer = document.getElementById('toast-icon-container');
      const toastTitle = document.getElementById('toast-title');
      const toastMessage = document.getElementById('toast-message');
      
      if (type === 'success') {
        toast.classList.remove('border-red-200', 'border-amber-200');
        toast.classList.add('border-green-200');
        toastIconContainer.classList.remove('bg-red-100', 'bg-amber-100');
        toastIconContainer.classList.add('bg-green-100');
        toastIcon.classList.remove('text-red-600', 'text-amber-600');
        toastIcon.classList.add('text-green-600');
        toastIcon.textContent = 'check_circle';
        toastTitle.classList.remove('text-red-600', 'text-amber-600');
        toastTitle.classList.add('text-green-600');
      } else if (type === 'warning') {
        toast.classList.remove('border-red-200', 'border-green-200');
        toast.classList.add('border-amber-200');
        toastIconContainer.classList.remove('bg-red-100', 'bg-green-100');
        toastIconContainer.classList.add('bg-amber-100');
        toastIcon.classList.remove('text-red-600', 'text-green-600');
        toastIcon.classList.add('text-amber-600');
        toastIcon.textContent = 'warning';
        toastTitle.classList.remove('text-red-600', 'text-green-600');
        toastTitle.classList.add('text-amber-600');
      } else {
        toast.classList.remove('border-green-200', 'border-amber-200');
        toast.classList.add('border-red-200');
        toastIconContainer.classList.remove('bg-green-100', 'bg-amber-100');
        toastIconContainer.classList.add('bg-red-100');
        toastIcon.classList.remove('text-green-600', 'text-amber-600');
        toastIcon.classList.add('text-red-600');
        toastIcon.textContent = 'error';
        toastTitle.classList.remove('text-green-600', 'text-amber-600');
        toastTitle.classList.add('text-red-600');
      }
      
      toastTitle.textContent = title;
      toastMessage.textContent = message;
      toastMessage.classList.add('whitespace-pre-line');
      
      toast.classList.remove('hidden');
      setTimeout(() => {
        toast.classList.remove('translate-x-[400px]');
      }, 100);
      
      setTimeout(() => {
        toast.classList.add('translate-x-[400px]');
        setTimeout(() => {
          toast.classList.add('hidden');
          toastMessage.classList.remove('whitespace-pre-line');
        }, 500);
      }, 5000);
    }
  // Reminder email handler
  document.querySelectorAll('.reminder-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
      const appId = this.dataset.appId;
      if (!appId) return;
      if (!confirm('Send reminder email to the applicant?')) return;

      try {
        const formData = new FormData();
        formData.append('application_id', appId);
        const response = await fetch('send_reminder_email.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        if (result.success) {
          showToast('success', 'Email Sent', result.message);
        } else {
          showToast('error', 'Error', result.message || 'Failed to send email.');
        }
      } catch (e) {
        showToast('error', 'Error', 'Network error while sending reminder.');
      }
    });
  });

  // Single document verification handler
  document.querySelectorAll('.verify-single-doc-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const appId = this.dataset.appId;
      const docType = this.dataset.docType;
      
      let label = "all documents";
      if (docType === "aadhaar") label = "Aadhaar";
      if (docType === "pan") label = "PAN";

      if (!confirm(`Mark ${label} as Verified for this applicant?`)) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('application_id', appId);
        formData.append('verification_status', 'Verified');
        formData.append('verification_type', docType);

        const response = await fetch('update_verification_status.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (result.success) {
          showToast('success', 'Document Verified', result.message);
          setTimeout(() => location.reload(), 1300);
        } else {
          showToast('error', 'Verification Failed', result.message || 'Failed to update verification status.');
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to submit verification request.');
        console.error(error);
      }
    });
  });

  // Send HOD approval handler
  document.querySelectorAll('.send-hod-approval-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const appId = this.dataset.appId;
      if (!confirm('Are you sure you want to send a HOD approval request email?')) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('application_id', appId);
        formData.append('new_status', 'HOD Approval Pending');
        formData.append('notes', 'Initiated HOD approval flow via HR review button.');

        const response = await fetch('update_application_status.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (result.success) {
          showToast('success', 'Approval Sent', result.message || 'HOD approval request email sent successfully.');
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast('error', 'Failed', result.message || 'Failed to send HOD approval.');
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to request HOD approval.');
        console.error(error);
      }
    });
  });

  // Direct Select Student handler
  document.querySelectorAll('.select-student-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const appId = this.dataset.appId;
      if (!confirm('Are you sure you want to select this student for the internship?')) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('application_id', appId);
        formData.append('new_status', 'Selected');
        formData.append('notes', 'Candidate selected directly by HR.');

        const response = await fetch('update_application_status.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (result.success) {
          showToast('success', 'Student Selected', result.message || 'Student has been successfully selected!');
          setTimeout(() => location.reload(), 1300);
        } else {
          showToast('error', 'Selection Failed', result.message || 'Failed to update candidate status.');
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to select student.');
        console.error(error);
      }
    });
  });

  document.querySelectorAll('.archive-app-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const appId = this.dataset.appId;
      const appName = this.dataset.name || 'this application';
      const status = (this.dataset.status || '').trim();
      const protectedStatuses = ['Applied', 'HR Review', 'Shortlisted', 'Exam Mail Sent', 'HOD Pending', 'HOD Approved', 'Selected', 'Project Assigned', 'Active Intern'];

      if (protectedStatuses.includes(status)) {
        showToast('warning', 'Archive Restricted', 'Only completed or closed applications can be archived.');
        return;
      }

      if (!confirm(`Archive ${appName}'s application? It will be moved to the archived list.`)) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('app_id', appId);

        const response = await fetch('archive_application.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (result.success) {
          showToast('success', 'Archived', result.message || 'Application archived successfully.');
          setTimeout(() => location.reload(), 1300);
        } else {
          showToast('error', 'Archive Failed', result.message || 'Unable to archive application.');
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to archive application.');
        console.error(error);
      }
    });
  });

  function getBulkExamBaseUrl() {
    const pathname = window.location.pathname || '/';
    const parts = pathname.split('/').filter(Boolean);
    if (parts.length <= 1) {
      return window.location.origin;
    }
    const appRoot = '/' + parts.slice(0, -1).join('/');
    return window.location.origin + appRoot;
  }

  function openBulkExamComposeModal(selectedIds, selectedRows) {
    const modal = document.getElementById('bulk-exam-modal');
    const form = document.getElementById('bulk-exam-form');
    const selectedCountEl = document.getElementById('bulk-exam-selected-count');
    const recipientsEl = document.getElementById('bulk-exam-recipients');
    const previewEl = document.getElementById('bulk-exam-link-preview');
    const subjectInput = document.getElementById('bulk-exam-subject');
    const messageInput = document.getElementById('bulk-exam-message');

    if (!modal || !form || !selectedCountEl || !recipientsEl || !previewEl || !subjectInput || !messageInput) {
      return;
    }

    const selectedRowsData = selectedRows || bulkRows.filter(row => !row.disabled && row.checked);
    const recipients = selectedRowsData
      .map(row => ({
        name: row.dataset.studentName || 'Student',
        email: row.dataset.studentEmail || ''
      }))
      .filter(item => item.email);

    selectedCountEl.textContent = `${selectedRowsData.length} student${selectedRowsData.length === 1 ? '' : 's'} selected`;
    recipientsEl.innerHTML = recipients.length ? recipients.map(item => `<li class="text-sm text-slate-600">${item.name} â€” ${item.email}</li>`).join('') : '<li class="text-sm text-slate-600">No recipients available.</li>';

    const firstId = (selectedIds || []).find(Boolean);
    const previewUrl = firstId ? `${getBulkExamBaseUrl()}/student_test.php?application_id=${firstId}` : `${getBulkExamBaseUrl()}/student_test.php?application_id=APPLICATION_ID`;
    previewEl.textContent = previewUrl;

    subjectInput.value = 'Internship Assessment Link';
    messageInput.value = [
      'Dear Student,',
      '',
      'You have been shortlisted for the internship assessment.',
      'Please click the link below to start your test:',
      '',
      '{{EXAM_LINK}}',
      '',
      'Regards,',
      'HR Team'
    ].join('\n');

    form.querySelector('input[name="selected_count"]').value = selectedRowsData.length;
    form.querySelectorAll('input[name="application_ids[]"]').forEach(input => input.remove());
    (selectedIds || []).forEach(id => {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'application_ids[]';
      hidden.value = id;
      form.appendChild(hidden);
    });

    modal.classList.remove('hidden');
  }

  // Bulk Exam Form submission handler
  const bulkExamForm = document.getElementById('bulk-exam-form');
  if (bulkExamForm) {
    bulkExamForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const selectedIds = Array.from(this.querySelectorAll('input[name="application_ids[]"]')).map(input => input.value).filter(Boolean);
      if (selectedIds.length === 0) {
        showToast('error', 'No Selection', 'Please select at least one student.');
        return;
      }

      const submitBtn = document.getElementById('bulk-exam-submit-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending...';
      
      try {
        const formData = new FormData(this);
        formData.append('action', 'send_exam_link');
        
        const response = await fetch('hr_bulk_action.php', {
          method: 'POST',
          body: formData
        });

        let result = {};
        const responseText = await response.text();
        try {
          result = responseText ? JSON.parse(responseText) : {};
        } catch (parseError) {
          console.error('Bulk action parse error', parseError, responseText);
          result = { success: false, title: 'Failed', message: 'No exam links were sent.\nReason: The server returned an invalid response.' };
        }

        const toastType = result.type || (result.success ? 'success' : 'error');
        const toastTitle = result.title || (result.success ? 'Success' : 'Failed');
        const toastMessage = result.message || 'Bulk exam request completed.';

        if (result.success) {
          closeBulkExamModal();
          showToast(toastType, toastTitle, toastMessage);
          setTimeout(() => location.reload(), 1300);
        } else {
          closeBulkExamModal();
          showToast(toastType, toastTitle, toastMessage);
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send Exam Link';
        }
      } catch (error) {
        console.error('Bulk action failed', error);
        showToast('error', 'Failed', 'Bulk action failed. Please check server error/logs.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Exam Link';
      }
    });
  }
  
  window.closeBulkExamModal = function() {
    const modal = document.getElementById('bulk-exam-modal');
    const form = document.getElementById('bulk-exam-form');
    if (modal) modal.classList.add('hidden');
    if (form) {
      form.reset();
      form.querySelectorAll('input[name="application_ids[]"]').forEach(input => input.remove());
      const selectedCountInput = form.querySelector('input[name="selected_count"]');
      if (selectedCountInput) {
        selectedCountInput.value = '0';
      }
    }
  }

