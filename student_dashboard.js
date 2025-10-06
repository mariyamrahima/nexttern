//Get student ID from the DOM instead of relying on PHP injection
function getStudentId() {
    // Try to get from sidebar user ID
    const userIdElement = document.querySelector('.user-id');
    if (userIdElement) {
        const match = userIdElement.textContent.match(/ID:\s*(\w+)/);
        if (match) return match[1];
    }
    return null;
}

// Initialize user data
const userData = {
    id: getStudentId() || '<?php echo $student_id; ?>',
    role: 'student'
};

console.log('User data initialized:', userData);
// Enhanced showSection function that handles page refresh
function showSection(sectionId, linkElement) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });

    // Remove active class from all navigation links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });

    // Show the selected section and add active class to the link
    document.getElementById(sectionId).classList.add('active');
    linkElement.classList.add('active');
    
    // Update page title in header
    const titles = {
        'dashboard': 'Dashboard',
        'profile': 'My Profile', 
        'messages': 'Messages',
        'applications': 'Applications',
        'stories': 'Success Stories',
        'saved_courses': 'My Saved Courses'
    };
    document.querySelector('.header-title').textContent = titles[sectionId];
    
    // Load saved courses when section is accessed
    if (sectionId === 'saved_courses') {
        setTimeout(() => {
            loadSavedCourses();
        }, 100);
    }
    
    // Update URL without reloading
    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?page=" + sectionId;
    window.history.pushState({ path: newUrl }, '', newUrl);

    // Close mobile sidebar if open
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    }
}

// Enhanced function specifically for saved courses
function enhancedShowSection(sectionId, linkElement) {
    showSection(sectionId, linkElement);
}

// Fixed loadSavedCourses function with better error handling
function loadSavedCourses() {
    const savedCoursesList = document.getElementById('savedCoursesList');
    const savedCoursesEmpty = document.getElementById('savedCoursesEmpty');
    const savedCoursesCount = document.getElementById('savedCoursesCount');
    
    // Check if elements exist
    if (!savedCoursesList || !savedCoursesEmpty) {
        console.error('Required DOM elements not found for saved courses');
        return;
    }
    
    // Show loading state
    savedCoursesList.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading saved courses...</div>';
    savedCoursesList.style.display = 'block';
    savedCoursesEmpty.style.display = 'none';
    
    // Use the student_id from PHP session
    const userId = userData.id;
    const userType = userData.role;
    
    console.log('Loading saved courses for user:', userId, 'type:', userType);
    
    // Add timestamp to prevent caching
    const timestamp = new Date().getTime();
    const url = `get_saved_courses_detailed.php?user_id=${encodeURIComponent(userId)}&user_type=${encodeURIComponent(userType)}&t=${timestamp}`;
    
    fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        },
        cache: 'no-store'
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Parsed data:', data);
        
        if (data.success) {
            if (data.saved_courses && data.saved_courses.length > 0) {
                displaySavedCourses(data.saved_courses);
                
                // Update badge count
                if (savedCoursesCount) {
                    savedCoursesCount.textContent = data.saved_courses.length;
                    savedCoursesCount.style.display = 'inline-block';
                }
                
                savedCoursesEmpty.style.display = 'none';
                savedCoursesList.style.display = 'block';
            } else {
                // No saved courses found
                console.log('No saved courses found');
                savedCoursesEmpty.style.display = 'block';
                savedCoursesList.style.display = 'none';
                if (savedCoursesCount) {
                    savedCoursesCount.style.display = 'none';
                }
            }
        } else {
            throw new Error(data.message || 'Unknown error from server');
        }
    })
    .catch(error => {
        console.error('Error loading saved courses:', error);
        savedCoursesList.innerHTML = `
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> 
                Error loading saved courses: ${error.message}
                <button onclick="loadSavedCourses()" class="btn btn-secondary" style="margin-left: 1rem;">
                    <i class="fas fa-sync"></i> Try Again
                </button>
            </div>`;
        savedCoursesEmpty.style.display = 'none';
        savedCoursesList.style.display = 'block';
    });
}

function displaySavedCourses(courses) {
    const savedCoursesList = document.getElementById('savedCoursesList');
    const savedCoursesEmpty = document.getElementById('savedCoursesEmpty');
    
    if (!courses || courses.length === 0) {
        if (savedCoursesEmpty) savedCoursesEmpty.style.display = 'block';
        if (savedCoursesList) savedCoursesList.style.display = 'none';
        return;
    }
    
    savedCoursesList.innerHTML = courses.map((course, index) => `
        <div class="saved-course-item" data-course-id="${course.id || course.course_id}" style="opacity: 0; transform: translateY(20px); animation: fadeInUp 0.5s ease ${index * 100}ms forwards;">
            <div class="course-header">
                <div class="course-info">
                    <h4 class="course-title">
                        ${course.course_title || 'Untitled Course'}
                        <span class="course-type-badge" style="background: ${(course.course_type || 'self_paced') === 'self_paced' ? 'linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%)' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'}; color: white; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.7rem; margin-left: 0.5rem;">
                            ${(course.course_type || 'self_paced') === 'self_paced' ? 'SELF-PACED' : 'LIVE'}
                        </span>
                        <span class="free-badge" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); color: white; padding: 0.25rem 0.65rem; border-radius: 8px; font-size: 0.7rem; margin-left: 0.5rem; font-weight: 600;">
                            100% FREE
                        </span>
                    </h4>
                    <p class="course-category">${course.course_category || 'General'}</p>
                    <div class="course-meta">
                        <span class="meta-tag duration">
                            <i class="fas fa-clock"></i> ${course.duration || 'Duration not specified'}
                        </span>
                        <span class="meta-tag difficulty ${(course.difficulty_level || 'beginner').toLowerCase()}">
                            <i class="fas fa-signal"></i> ${course.difficulty_level || 'Beginner'}
                        </span>
                        <span class="meta-tag company">
                            <i class="fas fa-building"></i> ${course.company_name || 'Unknown Company'}
                        </span>
                    </div>
                </div>
                <div class="course-actions">
                    <button class="btn btn-secondary" onclick="viewCourseDetail(${course.id || course.course_id})">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn" onclick="unsaveCourse(${course.id || course.course_id})" style="background: var(--danger); color: white;">
                        <i class="fas fa-bookmark-slash"></i> Remove
                    </button>
                </div>
            </div>
            
            <div class="course-description">
                <p>${course.course_description || 'No description available'}</p>
            </div>
            
            ${course.skills_taught ? `
                <div class="course-skills">
                    ${course.skills_taught.split(',').slice(0, 5).map(skill => 
                        `<span class="skill-tag">${skill.trim()}</span>`
                    ).join('')}
                    ${course.skills_taught.split(',').length > 5 ? 
                        `<span class="skill-tag">+${course.skills_taught.split(',').length - 5} more</span>` : ''
                    }
                </div>
            ` : ''}
            
            <div class="course-footer">
                <div class="saved-info">
                    <small style="color: var(--slate-500);">
                        <i class="fas fa-bookmark"></i> 
                        Saved on ${new Date(course.saved_at).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'})}
                    </small>
                </div>
                ${course.certificate_provided ? 
                    '<div class="certificate-badge"><i class="fas fa-certificate"></i> Certificate Included</div>' : ''
                }
            </div>
        </div>
    `).join('');
    
    if (savedCoursesEmpty) savedCoursesEmpty.style.display = 'none';
    if (savedCoursesList) savedCoursesList.style.display = 'block';
}

function refreshSavedCourses() {
    const refreshBtn = document.getElementById('refreshBtn');
    if (!refreshBtn) return;
    
    const originalHtml = refreshBtn.innerHTML;
    
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    // Force a fresh load
    loadSavedCourses();
    
    setTimeout(() => {
        refreshBtn.innerHTML = originalHtml;
        refreshBtn.disabled = false;
    }, 1000);
}// FIX 2: Updated unsaveCourse function with better error handling
function unsaveCourse(courseId) {
    if (!confirm('Remove this course from your saved list?')) return;
    
    // Validate userData
    if (!userData || !userData.id) {
        console.error('User data not available');
        showNotification('Error: User session not found. Please refresh the page.', 'error');
        return;
    }
    
    console.log('Removing course:', courseId, 'for user:', userData.id);
    
    const formData = new FormData();
    formData.append('action', 'unsave');
    formData.append('course_id', courseId);
    formData.append('user_id', userData.id);
    formData.append('user_type', userData.role);
    
    // Show loading state
    const courseItem = document.querySelector(`[data-course-id="${courseId}"]`);
    if (courseItem) {
        courseItem.style.opacity = '0.5';
        courseItem.style.pointerEvents = 'none';
    }
    
    fetch('save_course.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        
        if (data.success) {
            // Remove the course item with animation
            if (courseItem) {
                courseItem.style.transform = 'translateX(-100%)';
                courseItem.style.opacity = '0';
                
                setTimeout(() => {
                    courseItem.remove();
                    
                    // Check if any courses left
                    const remainingCourses = document.querySelectorAll('.saved-course-item');
                    const savedCoursesEmpty = document.getElementById('savedCoursesEmpty');
                    const savedCoursesList = document.getElementById('savedCoursesList');
                    const savedCoursesCount = document.getElementById('savedCoursesCount');
                    
                    if (remainingCourses.length === 0) {
                        if (savedCoursesEmpty) savedCoursesEmpty.style.display = 'block';
                        if (savedCoursesList) savedCoursesList.style.display = 'none';
                        if (savedCoursesCount) savedCoursesCount.style.display = 'none';
                    } else {
                        if (savedCoursesCount) {
                            savedCoursesCount.textContent = remainingCourses.length;
                        }
                    }
                    
                    showNotification('Course removed from saved list!', 'success');
                }, 300);
            }
        } else {
            // Restore course item on error
            if (courseItem) {
                courseItem.style.opacity = '1';
                courseItem.style.pointerEvents = 'auto';
            }
            showNotification('Failed to remove course: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Restore course item on error
        if (courseItem) {
            courseItem.style.opacity = '1';
            courseItem.style.pointerEvents = 'auto';
        }
        showNotification('Error removing course: ' + error.message, 'error');
    });
}

function viewCourseDetail(courseId) {
    window.open('course_detail.php?id=' + courseId, '_blank');
}

// Initialize saved courses count and handle page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, user data:', userData);
    
    // Check current page and load saved courses if needed
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get('page');
    
    if (currentPage === 'saved_courses') {
        // If we're on saved courses page, load them immediately
        setTimeout(() => {
            loadSavedCourses();
        }, 500);
    }
    
    // Load initial saved courses count
    if (userData && userData.id) {
        const timestamp = new Date().getTime();
        fetch(`get_saved_courses.php?user_id=${encodeURIComponent(userData.id)}&user_type=${encodeURIComponent(userData.role)}&t=${timestamp}`, {
            cache: 'no-store',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.saved_courses && data.saved_courses.length > 0) {
                const savedCoursesCount = document.getElementById('savedCoursesCount');
                if (savedCoursesCount) {
                    savedCoursesCount.textContent = data.saved_courses.length;
                    savedCoursesCount.style.display = 'inline-block';
                }
            }
        })
        .catch(error => console.error('Error loading saved courses count:', error));
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page') || 'dashboard';
        const linkElement = document.querySelector(`.nav-link[onclick*="${page}"]`);
        if (linkElement) {
            showSection(page, linkElement);
        }
    });
});
// FIX 3: Enhanced notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(n => n.remove());
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `notification-toast alert-${type === 'error' ? 'error' : 'success'}`;
    
    const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
    notification.innerHTML = `
        <i class="fas ${icon}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="close-notification">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Add required CSS for notifications
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    .notification-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 320px;
        max-width: 500px;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 1rem;
        transform: translateX(400px);
        opacity: 0;
        transition: all 0.3s ease;
    }
    
    .notification-toast.show {
        transform: translateX(0);
        opacity: 1;
    }
    
    .notification-toast.alert-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }
    
    .notification-toast.alert-error {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    
    .notification-toast i:first-child {
        font-size: 1.5rem;
    }
    
    .notification-toast span {
        flex: 1;
        font-weight: 500;
    }
    
    .close-notification {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        padding: 0.25rem;
        opacity: 0.8;
        transition: opacity 0.2s;
    }
    
    .close-notification:hover {
        opacity: 1;
    }
`;
document.head.appendChild(notificationStyles);
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);


// Enhanced form validation
function validateForm(formElement) {
    let isValid = true;
    const requiredFields = formElement.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = 'var(--danger)';
            field.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
            isValid = false;
            
            // Remove error styling when user starts typing
            field.addEventListener('input', function() {
                this.style.borderColor = '';
                this.style.boxShadow = '';
            }, { once: true });
        } else {
            field.style.borderColor = 'var(--success)';
            field.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
        }
    });
    
    return isValid;
}

// Apply form validation to all forms
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
            const firstErrorField = this.querySelector('[required]:invalid, [required][value=""]');
            if (firstErrorField) {
                firstErrorField.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                firstErrorField.focus();
            }
        }
    });
});

// Fixed rating stars functionality
document.querySelectorAll('.rating-stars').forEach(ratingContainer => {
    const labels = ratingContainer.querySelectorAll('label');
    const inputs = ratingContainer.querySelectorAll('input[type="radio"]');
    
    // Add click handlers
    inputs.forEach((input, index) => {
        input.addEventListener('change', function() {
            updateStarDisplay(ratingContainer, this.value);
        });
    });
    
    // Add hover handlers
    labels.forEach((label) => {
        label.addEventListener('mouseenter', function() {
            const value = this.previousElementSibling.value;
            highlightStars(ratingContainer, value);
        });
    });
    
    // Reset on mouse leave
    ratingContainer.addEventListener('mouseleave', function() {
        const checkedInput = this.querySelector('input[type="radio"]:checked');
        if (checkedInput) {
            updateStarDisplay(ratingContainer, checkedInput.value);
        } else {
            highlightStars(ratingContainer, 0);
        }
    });
});

function highlightStars(container, rating) {
    const labels = container.querySelectorAll('label');
    labels.forEach((label) => {
        const input = label.previousElementSibling;
        if (parseInt(input.value) <= parseInt(rating)) {
            label.style.color = 'var(--warning)';
        } else {
            label.style.color = 'var(--slate-300)';
        }
    });
}

function updateStarDisplay(container, rating) {
    highlightStars(container, rating);
}

// ============================================
// 3. COURSE TYPE FILTER FUNCTION
// ============================================
function filterCoursesByType(type) {
    const allCourses = document.querySelectorAll('.application-item');
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    // Update active button state
    filterButtons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-type') === type) {
            btn.classList.add('active');
        }
    });
    
    // Filter courses
    let visibleCount = 0;
    allCourses.forEach(course => {
        const courseType = course.getAttribute('data-course-type');
        
        if (type === 'all') {
            course.classList.remove('hidden');
            course.style.display = 'block';
            visibleCount++;
        } else if (courseType === type) {
            course.classList.remove('hidden');
            course.style.display = 'block';
            visibleCount++;
        } else {
            course.classList.add('hidden');
            course.style.display = 'none';
        }
    });
    
    // Handle empty state
    const coursesContainer = document.getElementById('coursesContainer');
    const existingEmptyState = coursesContainer.querySelector('.filter-empty-state');
    
    if (visibleCount === 0) {
        if (!existingEmptyState) {
            const emptyState = document.createElement('div');
            emptyState.className = 'filter-empty-state';
            
            let typeLabel = 'courses';
            if (type === 'self_paced') typeLabel = 'Self-Paced courses';
            if (type === 'live') typeLabel = 'Live Session courses';
            
            emptyState.innerHTML = `
                <i class="fas fa-filter"></i>
                <h3>No ${typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1)} Found</h3>
                <p>You haven't enrolled in any ${typeLabel.toLowerCase()} yet.</p>
                <button class="btn btn-primary" onclick="filterCoursesByType('all')" style="margin-top: 1rem;">
                    <i class="fas fa-list"></i> Show All Courses
                </button>
            `;
            coursesContainer.appendChild(emptyState);
        }
    } else {
        if (existingEmptyState) {
            existingEmptyState.remove();
        }
    }
}

// File upload functionality
function setupFileUpload(uploadAreaId, inputId) {
    const uploadArea = document.getElementById(uploadAreaId);
    const fileInput = document.getElementById(inputId);
    
    if (!uploadArea || !fileInput) return;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, () => uploadArea.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('dragover'), false);
    });

    uploadArea.addEventListener('drop', handleDrop, false);

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        updateFileDisplay(files);
    }

    fileInput.addEventListener('change', function(e) {
        updateFileDisplay(e.target.files);
    });

    function updateFileDisplay(files) {
        const uploadText = uploadArea.querySelector('.upload-text');
        if (files.length > 0) {
            uploadText.innerHTML = `
                <h4>${files.length} file(s) selected</h4>
                <p>${Array.from(files).map(f => f.name).join(', ')}</p>
            `;
        }
    }
}

// Initialize file uploads
setupFileUpload('story-upload', 'story-images');
setupFileUpload('feedback-upload', 'feedback-files');

// Auto-refresh functionality
setInterval(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get('page');
    if (currentPage === 'messages') {
        const lastInteraction = Date.now() - (window.lastUserInteraction || 0);
        if (lastInteraction > 10000) {
            location.reload();
        }
    }
}, 30000);

// Track user interactions
document.addEventListener('click', function() {
    window.lastUserInteraction = Date.now();
});

document.addEventListener('scroll', function() {
    window.lastUserInteraction = Date.now();
});

// Handle keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key >= '1' && e.key <= '6') {
        const navLinks = document.querySelectorAll('.nav-link');
        const linkIndex = parseInt(e.key) - 1;
        if (navLinks[linkIndex]) {
            navLinks[linkIndex].click();
        }
    }
});

// Add loading states to form submissions
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            const originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            // Re-enable after 5 seconds as fallback
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }, 5000);
        }
    });
});

// Handle window resize for responsive behavior
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    }
});

// Profile photo upload functionality
document.addEventListener('DOMContentLoaded', function() {
    const photoUploadArea = document.getElementById('photoUploadArea');
    const photoInput = document.getElementById('profilePhotoInput');
    const photoForm = document.getElementById('photo-upload-form');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeFileBtn = document.getElementById('removeFileBtn');
    const photoPreview = document.getElementById('photoPreview');
    const previewImage = document.getElementById('previewImage');
    const uploadIcon = document.getElementById('uploadIcon');
    const uploadText = document.getElementById('uploadText');
    const photoSubmitBtn = document.getElementById('photoSubmitBtn');

    if (photoUploadArea && photoInput) {
        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            photoUploadArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            photoUploadArea.addEventListener(eventName, () => {
                photoUploadArea.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            photoUploadArea.addEventListener(eventName, () => {
                photoUploadArea.classList.remove('dragover');
            }, false);
        });

        photoUploadArea.addEventListener('drop', handleDrop, false);

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                photoInput.files = files;
                handleFileSelect(files[0]);
            }
        }

        // File input change
        photoInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        // Handle file selection
        function handleFileSelect(file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please select a valid image file (JPG, PNG, or GIF)');
                return;
            }

            // Validate file size (5MB limit)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File size must be less than 5MB');
                return;
            }

            // Show file info
            if (fileName) fileName.textContent = file.name;
            if (fileSize) fileSize.textContent = formatFileSize(file.size);
            if (fileInfo) fileInfo.classList.add('show');
            photoUploadArea.classList.add('has-file');

            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                if (previewImage) previewImage.src = e.target.result;
                if (photoPreview) photoPreview.classList.add('show');
                
                // Update upload area appearance
                if (uploadIcon) uploadIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                if (uploadText) {
                    uploadText.querySelector('h4').textContent = 'Photo Ready to Upload';
                    uploadText.querySelector('p').innerHTML = 'Click "Upload Now" to save your new profile photo<br><button type="button" class="btn btn-primary" onclick="document.getElementById(\'photoSubmitBtn\').click()" style="margin-top: 1rem;"><i class="fas fa-upload"></i> Upload Now</button>';
                }
            };
            reader.readAsDataURL(file);
        }

        // Remove file
        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                clearFileSelection();
            });
        }

        function clearFileSelection() {
            photoInput.value = '';
            if (fileInfo) fileInfo.classList.remove('show');
            if (photoPreview) photoPreview.classList.remove('show');
            photoUploadArea.classList.remove('has-file');
            
            // Reset upload area
            if (uploadIcon) uploadIcon.innerHTML = '<i class="fas fa-cloud-upload-alt"></i>';
            if (uploadText) {
                uploadText.querySelector('h4').textContent = 'Upload New Profile Photo';
                uploadText.querySelector('p').innerHTML = 'Drag and drop your photo here, or click to browse<br><div class="file-input-wrapper"><button type="button" class="file-select-btn"><i class="fas fa-image"></i> Choose Photo</button></div>';
            }
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Auto-submit form when file is selected
        photoInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                // Small delay to show preview
                setTimeout(() => {
                    if (photoForm) photoForm.submit();
                }, 1000);
            }
        });
    }

    // Resume upload functionality
    const resumeUploadArea = document.getElementById('resumeUploadArea');
    const resumeInput = document.getElementById('resumeFileInput');
    const resumeForm = document.getElementById('resume-upload-form');
    const resumeFileInfo = document.getElementById('resumeFileInfo');
    const resumeFileName = document.getElementById('resumeFileName');
    const resumeFileSize = document.getElementById('resumeFileSize');
    const removeResumeFileBtn = document.getElementById('removeResumeFileBtn');
    const resumeUploadIcon = document.getElementById('resumeUploadIcon');
    const resumeUploadText = document.getElementById('resumeUploadText');
    const resumeSubmitBtn = document.getElementById('resumeSubmitBtn');

    if (resumeUploadArea && resumeInput) {
        // Drag and drop functionality for resume
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            resumeUploadArea.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            resumeUploadArea.addEventListener(eventName, () => {
                resumeUploadArea.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            resumeUploadArea.addEventListener(eventName, () => {
                resumeUploadArea.classList.remove('dragover');
            }, false);
        });

        resumeUploadArea.addEventListener('drop', handleResumeDrop, false);

        function handleResumeDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                resumeInput.files = files;
                handleResumeFileSelect(files[0]);
            }
        }

        // Resume file input change
        resumeInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                handleResumeFileSelect(e.target.files[0]);
            }
        });

        // Handle resume file selection
        function handleResumeFileSelect(file) {
            // Validate file type
            const allowedExtensions = ['.pdf', '.doc', '.docx'];
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(fileExtension)) {
                alert('Please select a valid resume file (PDF, DOC, or DOCX)');
                return;
            }

            // Validate file size (10MB limit)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File size must be less than 10MB');
                return;
            }

            // Show file info
            if (resumeFileName) resumeFileName.textContent = file.name;
            if (resumeFileSize) resumeFileSize.textContent = formatFileSize(file.size);
            if (resumeFileInfo) resumeFileInfo.classList.add('show');
            resumeUploadArea.classList.add('has-file');

            // Update upload area appearance
            if (resumeUploadIcon) resumeUploadIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
            if (resumeUploadText) {
                resumeUploadText.querySelector('h4').textContent = 'Resume Ready to Upload';
                resumeUploadText.querySelector('p').innerHTML = 'Click "Upload Now" to save your resume<br><button type="button" class="btn btn-primary" onclick="document.getElementById(\'resumeSubmitBtn\').click()" style="margin-top: 1rem;"><i class="fas fa-upload"></i> Upload Now</button>';
            }
        }

        // Remove resume file
        if (removeResumeFileBtn) {
            removeResumeFileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                clearResumeFileSelection();
            });
        }

        function clearResumeFileSelection() {
            resumeInput.value = '';
            if (resumeFileInfo) resumeFileInfo.classList.remove('show');
            resumeUploadArea.classList.remove('has-file');
            
            // Reset upload area
            if (resumeUploadIcon) resumeUploadIcon.innerHTML = '<i class="fas fa-file-upload"></i>';
            if (resumeUploadText) {
                resumeUploadText.querySelector('h4').textContent = 'Upload Resume/CV';
                resumeUploadText.querySelector('p').innerHTML = 'Drag and drop your resume here, or click to browse<br><div class="file-input-wrapper"><button type="button" class="file-select-btn"><i class="fas fa-file-text"></i> Choose File</button></div>';
            }
        }

        // Auto-submit form when resume file is selected
        resumeInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                setTimeout(() => {
                    if (resumeForm) resumeForm.submit();
                }, 1000);
            }
        });
    }
});

// Remove resume function
function removeResume() {
    if (confirm('Are you sure you want to remove your current resume? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'student_dashboard.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remove_resume';
        input.value = '1';
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// JavaScript functions for Read More functionality
function toggleDescription(button) {
    const applicationItem = button.closest('.application-item');
    const shortDesc = applicationItem.querySelector('.description-short');
    const fullDesc = applicationItem.querySelector('.description-full');
    
    if (shortDesc.style.display === 'none') {
        // Currently showing full, switch to short
        shortDesc.style.display = 'inline';
        fullDesc.style.display = 'none';
        button.textContent = 'Read More';
    } else {
        // Currently showing short, switch to full
        shortDesc.style.display = 'none';
        fullDesc.style.display = 'inline';
        button.textContent = 'Read Less';
    }
}

function toggleCoverLetter(button) {
    const applicationItem = button.closest('.application-item');
    const shortCover = applicationItem.querySelector('.cover-short');
    const fullCover = applicationItem.querySelector('.cover-full');
    
    if (shortCover.style.display === 'none') {
        // Currently showing full, switch to short
        shortCover.style.display = 'block';
        fullCover.style.display = 'none';
        button.textContent = 'Read More';
    } else {
        // Currently showing short, switch to full
        shortCover.style.display = 'none';
        fullCover.style.display = 'block';
        button.textContent = 'Read Less';
    }
}

// Sidebar functionality
function toggleSidebarFromHeader() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    
    // For mobile screens (768px and below)
    if (window.innerWidth <= 768) {
        // Toggle mobile sidebar
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
    } else {
        // For desktop screens - toggle collapse/expand
        sidebar.classList.toggle('collapsed');
    }
}

// Mobile sidebar toggle (for overlay clicks)
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
}

// Handle window resize for responsive behavior
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    
    if (window.innerWidth > 768) {
        // Desktop mode - remove mobile classes
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
        
        // Auto-collapse sidebar on tablet screens
        if (window.innerWidth <= 1024) {
            sidebar.classList.add('collapsed');
        }
    } else {
        // Mobile mode - ensure sidebar is hidden and remove collapsed state
        sidebar.classList.remove('mobile-open', 'collapsed');
        overlay.classList.remove('active');
    }
});

// Initialize responsive behavior on page load
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    
    // Auto-collapse on tablet/smaller desktop screens only
    if (window.innerWidth > 768 && window.innerWidth <= 1024) {
        sidebar.classList.add('collapsed');
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page') || 'dashboard';
        const linkElement = document.querySelector(`.nav-link[onclick*="${page}"]`);
        if (linkElement) {
            showSection(page, linkElement);
        }
    });
    
    // Initialize current page
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get('page') || 'dashboard';
    const linkElement = document.querySelector(`.nav-link[onclick*="${currentPage}"]`);
    if (linkElement) {
        showSection(currentPage, linkElement);
    }
});

// Keyboard shortcut for sidebar toggle (Ctrl/Cmd + B)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        toggleSidebarFromHeader();
    }
});

// Touch gesture support for mobile sidebar
let touchStartX = null;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.touches[0].clientX;
});

document.addEventListener('touchend', function(e) {
    if (touchStartX === null) return;
    
    const touchEndX = e.changedTouches[0].clientX;
    const diffX = touchEndX - touchStartX;
    
    // Swipe right from left edge to open sidebar
    if (touchStartX < 50 && diffX > 100 && window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        if (!sidebar.classList.contains('mobile-open')) {
            sidebar.classList.add('mobile-open');
            overlay.classList.add('active');
        }
    }
    
    touchStartX = null;
});

// Course Type Filtering Function
function filterCoursesByType(type) {
    const coursesContainer = document.getElementById('coursesContainer');
    const courseItems = document.querySelectorAll('.application-item[data-course-type]');
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    // Update active button state
    filterButtons.forEach(btn => {
        if (btn.getAttribute('data-type') === type) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    let visibleCount = 0;
    
    // Filter courses
    courseItems.forEach(item => {
        const courseType = item.getAttribute('data-course-type');
        
        if (type === 'all' || courseType === type) {
            // Show course
            item.classList.remove('hidden');
            item.style.display = 'block';
            
            // Animate in
            setTimeout(() => {
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, visibleCount * 50);
            
            visibleCount++;
        } else {
            // Hide course
            item.classList.add('hidden');
            setTimeout(() => {
                item.style.display = 'none';
            }, 300);
        }
    });
    
    // Show/hide no results message
    showNoResultsMessage(coursesContainer, visibleCount, type);
    
    // Update URL parameter without reload
    const url = new URL(window.location);
    if (type !== 'all') {
        url.searchParams.set('filter', type);
    } else {
        url.searchParams.delete('filter');
    }
    window.history.pushState({}, '', url);
}

// Show no results message if needed
function showNoResultsMessage(container, visibleCount, filterType) {
    // Remove existing no results message
    const existingMessage = container.querySelector('.no-results-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // If no courses visible, show message
    if (visibleCount === 0) {
        const typeLabels = {
            'self_paced': 'self-paced',
            'live': 'live session',
            'all': ''
        };
        
        const noResultsHTML = `
            <div class="no-results-message">
                <i class="fas fa-search"></i>
                <h3>No ${typeLabels[filterType] || ''} courses found</h3>
                <p>You haven't enrolled in any ${typeLabels[filterType] || ''} courses yet.</p>
                <button class="btn btn-primary" onclick="window.location.href='index.php#courses'" style="margin-top: 1rem;">
                    <i class="fas fa-search"></i> Browse Available Courses
                </button>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', noResultsHTML);
    }
}

// Apply filter on page load if URL parameter exists
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const filterType = urlParams.get('filter');
    
    if (filterType && ['self_paced', 'live'].includes(filterType)) {
        // Small delay to ensure DOM is ready
        setTimeout(() => {
            filterCoursesByType(filterType);
        }, 100);
    }
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
// Withdrawal Confirmation Function
function confirmWithdrawal(courseTitle, courseType) {
    let message = `Are you sure you want to withdraw from "${courseTitle}"?\n\n`;
    
    if (courseType === 'live') {
        message += "⚠️ WARNING: This is a LIVE course. ";
        message += "You can only withdraw before the first session starts.\n\n";
    }
    
    message += "This action cannot be undone.";
    
    return confirm(message);
}

// Handle URL parameters for success/error messages
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const successParam = urlParams.get('success');
    const errorParam = urlParams.get('error');
    
    if (successParam === 'withdrawn') {
        showNotification('Successfully withdrawn from course!', 'success');
    }
    
    if (errorParam === 'session_started') {
        showNotification('Cannot withdraw: Live sessions have already started. Please contact support.', 'error');
    }
});

// Notification function
function showNotification(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
        ${message}
    `;
    
    const cardBody = document.querySelector('#applications .card-body');
    if (cardBody) {
        cardBody.insertBefore(alert, cardBody.firstChild);
        
        setTimeout(() => {
            alert.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }
}