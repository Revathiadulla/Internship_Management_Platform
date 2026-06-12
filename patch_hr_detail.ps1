$file = "c:\xampp\htdocs\IMP\hr\applicant_detail.php"
$content = Get-Content $file -Raw

# 1. Update status icons
$content = $content -replace "'Shortlisted'\s*=>\s*'star',", "'Shortlisted'    => 'star',`r`n      'Exam Link Sent' => 'forward_to_inbox',"

# 2. Update status colors
$content = $content -replace "'Shortlisted'\s*=>\s*'bg-amber-100 text-amber-600',", "'Shortlisted'    => 'bg-amber-100 text-amber-600',`r`n      'Exam Link Sent' => 'bg-purple-100 text-purple-600',"

# 3. Add 'Send Exam Link' block after 'hr_review' block
$searchBlock = @"
                    <?php if (`$current_status_key === 'hr_review'): ?>
                      <?php if (`$can_approve): ?>
                        <button type="button" onclick="if(confirm('Are you sure you want to shortlist this candidate?')) performTransition('Shortlisted');" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition-all shadow-sm cursor-pointer">
                          <span class="material-symbols-outlined text-base">star</span>
                          Shortlist Candidate
                        </button>
                      <?php else: ?>
                        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs">
                          <p class="font-bold text-amber-700 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">warning</span>
                            Aadhaar & PAN Verification Required
                          </p>
                          <p class="mt-1 text-amber-600">Please verify Aadhaar and PAN documents to shortlist this candidate.</p>
                        </div>
                      <?php endif; ?>
                    <?php endif; ?>
"@

$replaceBlock = @"
$searchBlock

                    <?php if (`$current_status_key === 'shortlisted'): ?>
                      <?php if (`$can_approve): ?>
                        <button type="button" id="btn-trigger-exam" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-purple-700 transition-all shadow-sm cursor-pointer">
                          <span class="material-symbols-outlined text-base">forward_to_inbox</span>
                          Send Exam Link
                        </button>
                      <?php endif; ?>
                    <?php endif; ?>
"@

$content = $content.Replace($searchBlock, $replaceBlock)

# 4. Update 'hr_review', 'shortlisted' block to 'exam_link_sent'
$content = $content.Replace("in_array(`$current_status_key, ['hr_review', 'shortlisted'])", "in_array(`$current_status_key, ['exam_link_sent'])")

# 5. Add 'exam_link_sent' to hr_review_stages
$content = $content.Replace("`$hr_review_stages = ['applied', 'shortlisted', 'hr_review', 'hod_pending', 'hod_approved'];", "`$hr_review_stages = ['applied', 'shortlisted', 'hr_review', 'exam_link_sent', 'hod_pending', 'hod_approved'];")

# 6. Append Javascript at the end before </body> or </script>
$jsScript = @"
    // Exam Modal Logic
    const examTrigger = document.getElementById('btn-trigger-exam');
    const examModal = document.getElementById('exam-compose-modal');
    const examClose = document.getElementById('exam-modal-close');
    const examCancel = document.getElementById('exam-modal-cancel');
    const examBackdrop = document.getElementById('exam-modal-backdrop');
    const examForm = document.getElementById('exam-compose-form');
    
    if(examTrigger) {
      examTrigger.addEventListener('click', () => {
        examModal.classList.remove('hidden');
      });
    }
    
    const closeExamModal = () => examModal.classList.add('hidden');
    if(examClose) examClose.addEventListener('click', closeExamModal);
    if(examCancel) examCancel.addEventListener('click', closeExamModal);
    if(examBackdrop) examBackdrop.addEventListener('click', closeExamModal);

    if(examForm) {
      examForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('exam-send-btn');
        const origText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="material-symbols-outlined text-base animate-spin">refresh</span> Sending...';
        submitBtn.disabled = true;

        try {
          const formData = new FormData(examForm);
          const response = await fetch('bulk_action.php', {
            method: 'POST',
            body: formData
          });
          const result = await response.json();
          if (result.success) {
            showToast('success', 'Success', 'Exam Link Sent successfully!');
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast('error', 'Failed', result.message || 'Error sending exam link.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
          }
        } catch (error) {
          showToast('error', 'Error', 'Failed to send exam link');
          submitBtn.disabled = false;
          submitBtn.innerHTML = origText;
        }
      });
    }
"@

$content = $content.Replace("<?php print_resume_not_found_js(); ?>", "$jsScript`r`n<?php print_resume_not_found_js(); ?>")

Set-Content -Path $file -Value $content
