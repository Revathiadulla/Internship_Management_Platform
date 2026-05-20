// profile.js - handles profile avatar click and profile modal actions

document.addEventListener('DOMContentLoaded', () => {
  const avatarBtn = document.getElementById('profile-toggle');
  const modal = document.getElementById('profile-modal');
  const overlay = document.getElementById('profile-modal-overlay');
  const closeBtn = document.getElementById('profile-modal-close');
  const form = document.getElementById('profile-edit-form');

  if (!avatarBtn || !modal) return;

  const openModal = () => {
    overlay.classList.remove('hidden');
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden'); // prevent background scroll
  };

  const closeModal = () => {
    overlay.classList.add('hidden');
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  };

  avatarBtn.addEventListener('click', (e) => {
    e.preventDefault();
    openModal();
  });

  // close on overlay click or close button
  if (overlay) {
    overlay.addEventListener('click', closeModal);
  }
  if (closeBtn) {
    closeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      closeModal();
    });
  }

  // handle Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeModal();
    }
  });

  // Submit form via AJAX
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(form);

      try {
        const response = await fetch('update_profile.php', {
          method: 'POST',
          body: formData,
        });
        const result = await response.json();
        if (result.success) {
          // Simple toast notification (you can replace with your UI toast)
          alert('Profile updated successfully.');
          // Reload to reflect changes
          location.reload();
        } else {
          alert('Error: ' + result.message);
        }
      } catch (err) {
        console.error(err);
        alert('An unexpected error occurred.');
      }
    });
  }
});
