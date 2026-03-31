document.addEventListener('DOMContentLoaded', () => {
  const user = requireAuth();
  populateNav();
  if (!user.role || user.role.toUpperCase() !== 'ADMIN') {
    alert('No tienes permisos para administrar usuarios');
    window.location.href = 'dashboard.html';
    return;
  }
  const tbody = document.querySelector('#userTable tbody');
  const btnAddUser = document.getElementById('btnAddUser');
  const userModal = new bootstrap.Modal(document.getElementById('userModal'));
  const roleSelect = document.getElementById('roleSelect');
  const form = document.getElementById('userForm');
  function loadUsers() {
    apiRequest('users.php').then(users => {
      tbody.innerHTML = '';
      users.forEach(u => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${u.id}</td>
          <td>${u.username}</td>
          <td>${u.role_name}</td>
        `;
        tbody.appendChild(tr);
      });
    });
  }
  function loadRoles() {
    apiRequest('roles.php').then(roles => {
      roleSelect.innerHTML = '';
      roles.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.textContent = r.name;
        roleSelect.appendChild(opt);
      });
    });
  }
  btnAddUser.addEventListener('click', () => {
    form.reset();
    loadRoles();
    userModal.show();
  });
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const username = document.getElementById('newUsername').value;
    const password = document.getElementById('newPassword').value;
    const role_id = parseInt(roleSelect.value);
    // Manejar subida de foto si existe
    let photo_path = null;
    const photoFile = document.getElementById('userPhoto') ? document.getElementById('userPhoto').files[0] : null;
    if (photoFile) {
      const fd = new FormData();
      fd.append('file', photoFile);
      try {
        const resp = await fetch(`${API_BASE}/upload_image.php`, { method: 'POST', body: fd });
        const res = await resp.json();
        if (res.path) {
          // Ajustar la ruta para frontend
          photo_path = res.path.replace('/backend', '..');
        } else if (res.error) {
          alert(res.error);
        }
      } catch (err) {
        console.error('Error al subir foto', err);
      }
    }
    apiRequest('users.php', 'POST', {username, password, role_id, photo_path}).then(() => {
      userModal.hide();
      loadUsers();
    }).catch(err => alert(err.error || 'Error al crear usuario'));
  });
  loadUsers();
});