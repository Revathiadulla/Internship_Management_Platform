import re

def rebuild_file():
    filepath = 'hr/applicant_detail.php'
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Find the end of the main content
    split_point = content.find('  </main>')
    if split_point == -1:
        print("Could not find </main>")
        return
        
    clean_top = content[:split_point + 9]
    
    modal_html = """

  <!-- Exam Compose Modal -->
  <div id="exam-compose-modal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" id="exam-modal-backdrop"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
          <div class="flex items-center gap-2 text-white">
            <span class="material-symbols-outlined">mail</span>
            <h3 class="text-lg font-bold">Send Exam Link</h3>
          </div>
          <button type="button" id="exam-modal-close" class="text-white/80 hover:text-white transition-colors">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form id="exam-compose-form" class="p-6 space-y-4">
          <input type="hidden" name="action" value="send_exam_link">
          <input type="hidden" name="selected_ids[]" value="<?php echo $app_id; ?>">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">To (Student Email)</label>
            <input type="text" name="to" value="<?php echo htmlspecialchars($email); ?>" 
                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" required readonly>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Subject</label>
            <input type="text" name="subject" value="Exam Link for Internship Selection" 
                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" required>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Exam Link</label>
            <input type="url" name="exam_link" placeholder="https://exam-platform.com/test/123" 
                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" required>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Message</label>
            <textarea name="message" rows="5" 
                      class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" required>Dear <?php echo htmlspecialchars($full_name); ?>,

You have been shortlisted for the <?php echo htmlspecialchars($d['internship_title'] ?? 'internship'); ?> program.

Please click the link below to take your assessment:
{{EXAM_LINK}}

Best regards,
HR Team</textarea>
          </div>
          <div class="flex gap-3 pt-2">
            <button type="submit" id="exam-send-btn"
                    class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-purple-700 transition-all shadow-sm">
              <span class="material-symbols-outlined text-base">send</span>
              Send Exam Link
            </button>
            <button type="button" id="exam-modal-cancel"
                    class="inline-flex items-center justify-center rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 transition-all">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>"""

    # Now we find the FIRST occurrence of the Toast Notification in the file
    toast_pos = content.find('  <!-- Toast Notification -->')
    # And we find the FIRST occurrence of `// Rejection UI toggles` to get the script
    script_start = content.find('  <script>', toast_pos)
    
    # Let's find where the original script ends
    # We look for the FIRST `rejection-reason');` inside the script
    reject_reason_pos = content.find("const rejectionReasonInput = document.getElementById('rejection-reason');", script_start)
    if reject_reason_pos == -1:
        print("reject_reason_pos not found")
        return
        
    script_end = content.find('    if (btnTriggerReject) {', reject_reason_pos)
    
    if script_end == -1:
        print("script end not found")
        return
        
    # Find the end of the rejection event listener
    rejection_listener_end = content.find("  </script>", script_end)
    if rejection_listener_end == -1:
        print("</script> not found")
        return
        
    # The original script
    original_script = content[script_start:rejection_listener_end]

    # Add the missing exam link logic to the end of the script
    exam_logic = """
    // Exam Modal Logic
    const btnSendExamLink = document.getElementById('btn-trigger-exam');
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
          const response = await fetch('send_exam_link.php', {
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
  </script>
<?php print_resume_not_found_js(); ?>
</body>
</html>
"""

    # We need to inject `const workflowButtons = [btnSendConfLetter, btnSendExamLink];`
    # Let's see if workflowButtons exists in original_script
    if 'const workflowButtons' in original_script:
        original_script = re.sub(r'const workflowButtons = \[.*?\];', 'const workflowButtons = [btnSendConfLetter, btnSendExamLink];', original_script)
    else:
        # if not, we can inject it right after `rejectionReasonInput`
        original_script = original_script.replace(
            "const rejectionReasonInput = document.getElementById('rejection-reason');",
            "const rejectionReasonInput = document.getElementById('rejection-reason');\n    const btnSendExamLink = document.getElementById('btn-trigger-exam');\n    const workflowButtons = [btnSendConfLetter, btnSendExamLink];"
        )
        
    toast_section = content[toast_pos:script_start]
        
    new_content = clean_top + "\n" + modal_html + "\n\n" + toast_section + original_script + "\n" + exam_logic
    
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("File rebuilt successfully!")

rebuild_file()
