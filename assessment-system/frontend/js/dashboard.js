/**
 * dashboard.js - Frontend JavaScript untuk Assessment System
 * 
 * FASE 2: Fetch API, render statistik, auto-AI feedback
 * Vanilla JS tanpa framework, responsive mobile
 */

// ============================================
// KONFIGURASI GLOBAL
// ============================================
const API_BASE_URL = '/api';
const STORAGE_KEY_API = 'assessment_api_key';
const STORAGE_KEY_ROLE = 'assessment_user_role';

// ============================================
// STATE MANAGEMENT
// ============================================
const AppState = {
    apiKey: null,
    role: null,
    currentClass: null,
    isLoading: false,
    
    init() {
        this.apiKey = localStorage.getItem(STORAGE_KEY_API);
        this.role = localStorage.getItem(STORAGE_KEY_ROLE);
    },
    
    setApiKey(key) {
        this.apiKey = key;
        localStorage.setItem(STORAGE_KEY_API, key);
    },
    
    setRole(role) {
        this.role = role;
        localStorage.setItem(STORAGE_KEY_ROLE, role);
    },
    
    logout() {
        this.apiKey = null;
        this.role = null;
        localStorage.removeItem(STORAGE_KEY_API);
        localStorage.removeItem(STORAGE_KEY_ROLE);
    },
    
    isAuthenticated() {
        return !!this.apiKey;
    }
};

// ============================================
// API CLIENT
// ============================================
const APIClient = {
    /**
     * Buat header dengan API Key
     */
    getHeaders() {
        return {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${AppState.apiKey}`
        };
    },
    
    /**
     * Fetch wrapper dengan error handling
     */
    async request(endpoint, options = {}) {
        const url = `${API_BASE_URL}${endpoint}`;
        
        const config = {
            ...options,
            headers: {
                ...this.getHeaders(),
                ...(options.headers || {})
            }
        };
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Terjadi kesalahan');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    // Endpoint wrappers
    async login(apiKey) {
        // Validasi API key dengan request ke endpoint profil
        const response = await this.request('/v1/students', { method: 'GET' });
        return response;
    },
    
    async getStudents(classId = null) {
        let endpoint = '/v1/students';
        if (classId) {
            endpoint += `?class_id=${classId}`;
        }
        return this.request(endpoint);
    },
    
    async submitAssessment(data) {
        return this.request('/v1/assessments', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    async getFeedback(assessmentData) {
        return this.request('/v1/feedback', {
            method: 'POST',
            body: JSON.stringify(assessmentData)
        });
    },
    
    async getStatistics(classId) {
        return this.request(`/v1/statistics?class_id=${classId}`);
    },
    
    async exportRapor(classId, subject = null) {
        let endpoint = `/v1/export/rapor?class_id=${classId}`;
        if (subject) {
            endpoint += `&subject=${subject}`;
        }
        // Untuk download, buka di window baru
        window.open(`${API_BASE_URL}${endpoint}`, '_blank');
    },
    
    async getStudentProfile(studentCode) {
        return this.request(`/v1/profile/${studentCode}`);
    },
    
    async getAccommodations() {
        return this.request('/v1/accommodations');
    },
    
    async recommendAccommodations(studentProfile) {
        return this.request('/v1/accommodations/recommend', {
            method: 'POST',
            body: JSON.stringify({ student_profile: studentProfile })
        });
    }
};

// ============================================
// UI HELPERS
// ============================================
const UI = {
    /**
     * Tampilkan loading overlay
     */
    showLoading(message = 'Memuat...') {
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.id = 'loadingOverlay';
        overlay.innerHTML = `
            <div class="card text-center">
                <div class="spinner" style="margin: 0 auto 1rem;"></div>
                <p>${message}</p>
            </div>
        `;
        document.body.appendChild(overlay);
        AppState.isLoading = true;
    },
    
    /**
     * Sembunyikan loading overlay
     */
    hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.remove();
        }
        AppState.isLoading = false;
    },
    
    /**
     * Tampilkan alert/notifikasi
     */
    showAlert(message, type = 'success') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;
        
        const container = document.querySelector('.container') || document.body;
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto hide setelah 5 detik
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            alertDiv.style.transition = 'opacity 0.3s';
            setTimeout(() => alertDiv.remove(), 300);
        }, 5000);
    },
    
    /**
     * Format angka sebagai nilai
     */
    formatScore(score) {
        return Number(score).toFixed(2);
    },
    
    /**
     * Dapatkan badge class untuk predikat
     */
    getPredicateBadge(predicate) {
        const badges = {
            'PBS': 'badge-pbs',
            'BSH': 'badge-bsh',
            'BC': 'badge-bc',
            'BB': 'badge-bb'
        };
        return badges[predicate] || '';
    }
};

// ============================================
// LOGIN HANDLER
// ============================================
async function handleLogin(event) {
    event.preventDefault();
    
    const apiKeyInput = document.getElementById('apiKeyInput');
    const apiKey = apiKeyInput.value.trim();
    
    if (!apiKey) {
        UI.showAlert('Masukkan API Key Anda', 'error');
        return;
    }
    
    UI.showLoading('Memvalidasi API Key...');
    
    try {
        AppState.setApiKey(apiKey);
        
        // Test koneksi
        await APIClient.getStudents();
        
        // Simpan role (dalam implementasi nyata, dapatkan dari response)
        AppState.setRole('guru'); // Default, seharusnya dari response
        
        UI.showAlert('Login berhasil!', 'success');
        
        // Redirect ke dashboard
        setTimeout(() => {
            window.location.href = '/frontend/index.html#dashboard';
        }, 1000);
        
    } catch (error) {
        UI.hideLoading();
        UI.showAlert(`Login gagal: ${error.message}`, 'error');
        AppState.logout();
    }
}

// ============================================
// DASHBOARD RENDERER
// ============================================
async function renderDashboard() {
    if (!AppState.isAuthenticated()) {
        window.location.href = '/frontend/index.html#login';
        return;
    }
    
    UI.showLoading('Memuat dashboard...');
    
    try {
        // Load statistik kelas
        await loadClassStatistics();
        
        // Load daftar siswa
        await loadStudentList();
        
    } catch (error) {
        UI.showAlert(`Gagal memuat dashboard: ${error.message}`, 'error');
    } finally {
        UI.hideLoading();
    }
}

async function loadClassStatistics() {
    // Data dummy untuk demo (ganti dengan API call sesungguhnya)
    const statistics = {
        count: 28,
        mean: 78.5,
        median: 80,
        max: 95,
        min: 55,
        distribution: {
            PBS: 8,
            BSH: 12,
            BC: 6,
            BB: 2
        }
    };
    
    const statsContainer = document.getElementById('statisticsGrid');
    if (!statsContainer) return;
    
    statsContainer.innerHTML = `
        <div class="stat-card">
            <div class="stat-label">Jumlah Siswa</div>
            <div class="stat-value">${statistics.count}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Nilai Rata-rata</div>
            <div class="stat-value">${UI.formatScore(statistics.mean)}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Nilai Tertinggi</div>
            <div class="stat-value">${UI.formatScore(statistics.max)}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Nilai Terendah</div>
            <div class="stat-value">${UI.formatScore(statistics.min)}</div>
        </div>
    `;
}

async function loadStudentList() {
    // Data dummy untuk demo
    const students = [
        { code: 'SIS001', name: 'Ahmad Rizki', score: 85, predicate: 'BSH', class: '4A' },
        { code: 'SIS002', name: 'Budi Santoso', score: 72, predicate: 'BSH', class: '4A' },
        { code: 'SIS003', name: 'Citra Dewi', score: 92, predicate: 'PBS', class: '4A' },
        { code: 'SIS004', name: 'Doni Pratama', score: 68, predicate: 'BC', class: '4A' },
        { code: 'SIS005', name: 'Eka Putri', score: 88, predicate: 'BSH', class: '4A' }
    ];
    
    const tableBody = document.getElementById('studentTableBody');
    if (!tableBody) return;
    
    tableBody.innerHTML = students.map(student => `
        <tr>
            <td>${student.code}</td>
            <td>${student.name}</td>
            <td>${student.class}</td>
            <td>${UI.formatScore(student.score)}</td>
            <td><span class="badge ${UI.getPredicateBadge(student.predicate)}">${student.predicate}</span></td>
            <td>
                <button class="btn btn-outline btn-sm" onclick="viewStudentProfile('${student.code}')">
                    Lihat Profil
                </button>
            </td>
        </tr>
    `).join('');
}

// ============================================
// ASSESSMENT FORM HANDLER
// ============================================
async function handleAssessmentSubmit(event) {
    event.preventDefault();
    
    const formData = {
        student_code: document.getElementById('studentCode').value,
        subject: document.getElementById('subject').value,
        learning_objective: document.getElementById('learningObjective').value,
        score: parseFloat(document.getElementById('score').value),
        accommodations: getSelectedAccommodations()
    };
    
    // Validasi
    if (!formData.student_code || !formData.score) {
        UI.showAlert('Lengkapi semua field yang diperlukan', 'error');
        return;
    }
    
    UI.showLoading('Menyimpan asesmen...');
    
    try {
        // Submit asesmen
        const result = await APIClient.submitAssessment(formData);
        
        // Auto-trigger feedback AI
        if (result.status === 'success') {
            UI.showAlert('Asesmen berhasil disimpan!', 'success');
            
            // Trigger feedback otomatis
            await triggerAutoFeedback(formData);
            
            // Reset form
            event.target.reset();
        }
        
    } catch (error) {
        UI.showAlert(`Gagal menyimpan: ${error.message}`, 'error');
    } finally {
        UI.hideLoading();
    }
}

async function triggerAutoFeedback(assessmentData) {
    try {
        const feedback = await APIClient.getFeedback(assessmentData);
        
        if (feedback.status === 'success' && feedback.data) {
            // Tampilkan feedback di modal atau alert
            showFeedbackModal(feedback.data);
        }
    } catch (error) {
        console.warn('Auto-feedback gagal:', error);
        // Jangan tampilkan error ke user, ini hanya auto-trigger
    }
}

function showFeedbackModal(feedbackData) {
    const modal = document.getElementById('feedbackModal');
    if (!modal) {
        alert('Feedback AI:\n\n' + JSON.stringify(feedbackData, null, 2));
        return;
    }
    
    const content = document.getElementById('feedbackContent');
    if (content) {
        content.innerHTML = `
            <h3>Feedback AI untuk ${feedbackData.student_name || assessmentData.student_code}</h3>
            <div class="mt-2">
                <strong>Nilai:</strong> ${feedbackData.score || '-'}<br>
                <strong>Predikat:</strong> ${feedbackData.predicate || '-'}<br>
                <strong>Deskripsi:</strong><br>
                <p class="mt-1">${feedbackData.description || '-'}</p>
            </div>
            <div class="mt-2">
                <strong>Rekomendasi:</strong><br>
                <p class="mt-1">${feedbackData.recommendation || '-'}</p>
            </div>
        `;
    }
    
    modal.classList.add('active');
}

function getSelectedAccommodations() {
    const checkboxes = document.querySelectorAll('input[name="accommodations"]:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

// ============================================
// STUDENT PROFILE VIEWER
// ============================================
async function viewStudentProfile(studentCode) {
    UI.showLoading('Memuat profil siswa...');
    
    try {
        const profile = await APIClient.getStudentProfile(studentCode);
        
        if (profile.status === 'success' && profile.data) {
            showProfileModal(profile.data);
        }
        
    } catch (error) {
        UI.showAlert(`Gagal memuat profil: ${error.message}`, 'error');
    } finally {
        UI.hideLoading();
    }
}

function showProfileModal(profileData) {
    const modal = document.getElementById('profileModal');
    if (!modal) {
        alert('Profil Siswa:\n\n' + JSON.stringify(profileData, null, 2));
        return;
    }
    
    const content = document.getElementById('profileContent');
    if (content) {
        content.innerHTML = `
            <h3>Profil: ${profileData.nama || profileData.student_code}</h3>
            <div class="mt-2">
                <p><strong>NISN:</strong> ${profileData.nisn || '-'}</p>
                <p><strong>Kelas:</strong> ${profileData.kelas || '-'}</p>
                <p><strong>Rata-rata:</strong> ${profileData.statistics?.average_score || '-'}</p>
                <p><strong>Total Asesmen:</strong> ${profileData.statistics?.total_assessments || 0}</p>
            </div>
            <div class="mt-2">
                <strong>Riwayat Asesmen:</strong>
                <table class="data-table mt-1">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Mapel</th>
                            <th>Nilai</th>
                            <th>Predikat</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(profileData.assessment_history || []).map(a => `
                            <tr>
                                <td>${a.date}</td>
                                <td>${a.subject}</td>
                                <td>${a.score}</td>
                                <td><span class="badge ${UI.getPredicateBadge(a.predicate)}">${a.predicate}</span></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
    
    modal.classList.add('active');
}

// ============================================
// EXPORT HANDLER
// ============================================
function handleExport() {
    const classId = document.getElementById('exportClassId')?.value;
    const subject = document.getElementById('exportSubject')?.value;
    
    if (!classId) {
        UI.showAlert('Pilih kelas terlebih dahulu', 'warning');
        return;
    }
    
    UI.showAlert('Mengunduh file CSV...', 'success');
    APIClient.exportRapor(classId, subject);
}

// ============================================
// MODAL CONTROLLER
// ============================================
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modal saat klik backdrop
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('active');
    }
});

// ============================================
// LOGOUT HANDLER
// ============================================
function handleLogout() {
    if (confirm('Apakah Anda yakin ingin keluar?')) {
        AppState.logout();
        window.location.href = '/frontend/index.html#login';
    }
}

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    // Init state
    AppState.init();
    
    // Check current page/hash
    const hash = window.location.hash || '#login';
    
    // Setup login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
        
        // Jika sudah login, redirect ke dashboard
        if (AppState.isAuthenticated() && hash === '#login') {
            window.location.href = '#dashboard';
        }
    }
    
    // Setup dashboard
    if (hash === '#dashboard') {
        renderDashboard();
    }
    
    // Setup assessment form
    const assessmentForm = document.getElementById('assessmentForm');
    if (assessmentForm) {
        assessmentForm.addEventListener('submit', handleAssessmentSubmit);
    }
    
    // Setup export button
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', handleExport);
    }
    
    // Setup logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
    
    // Update user info in header
    const userInfo = document.getElementById('userInfo');
    if (userInfo && AppState.role) {
        userInfo.textContent = `Halo, ${AppState.role.toUpperCase()}`;
    }
});

// Expose functions globally for inline handlers
window.viewStudentProfile = viewStudentProfile;
window.closeModal = closeModal;
window.handleLogout = handleLogout;
