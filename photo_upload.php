<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .content {
            padding: 30px;
        }

        .add-btn {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.4);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 14px;
        }

        .student-row {
            transition: all 0.3s ease;
        }

        .student-row:hover {
            background: #f8f9fa;
            transform: scale(1.01);
        }

        /* Profile Image Styles */
        .profile-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 3px solid #3498db;
            transition: all 0.3s ease;
        }

        .profile-image:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .no-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e5e7eb, #d1d5db);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 20px;
            border: 3px solid #e5e7eb;
            margin: 0 auto;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: block;
        }

        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            transform: scale(0.7);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #e74c3c;
        }

        .modal-body {
            padding: 30px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        /* File Upload Styles */
        .file-upload-container {
            position: relative;
        }

        .file-upload-input {
            display: none;
        }

        .file-upload-btn {
            display: inline-block;
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            border: none;
        }

        .file-upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(155, 89, 182, 0.3);
        }

        .file-preview {
            margin-top: 15px;
            text-align: center;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .preview-image:hover {
            transform: scale(1.05);
        }

        .crop-container {
            margin-top: 20px;
        }

        .crop-area {
            width: 300px;
            height: 200px;
            margin: 0 auto;
            border: 2px solid #3498db;
            border-radius: 8px;
            overflow: hidden;
        }

        .crop-area img {
            max-width: 100%;
        }

        /* Button Styles */
        .btn-save, .btn-cancel {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
            transition: all 0.3s ease;
        }

        .btn-save {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-sm {
            font-size: 12px;
            padding: 8px 15px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 10px;
        }

        .edit-btn, .delete-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .edit-btn {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }

        .edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(243, 156, 18, 0.3);
        }

        .delete-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(231, 76, 60, 0.3);
        }

        /* Image Viewer Modal */
        .image-viewer-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            backdrop-filter: blur(5px);
        }

        .image-viewer-content {
            position: relative;
            margin: 5% auto;
            max-width: 400px;
            max-height: 400px;
        }

        .image-viewer-content img {
            width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            max-width: 400px;
            max-height: 400px;
            object-fit: contain;
        }

        .image-close {
            position: absolute;
            top: -40px;
            right: 0;
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
        }

        .image-close:hover {
            color: #e74c3c;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-grid .form-group:last-child {
            grid-column: 1 / -1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-graduation-cap"></i> Student Management System</h1>
            <p>Manage Student Records with Photo Upload</p>
        </div>
        
        <div class="content">
            <button class="add-btn" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Student
            </button>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Student ID</th>
                            <th>Course</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                        <!-- Sample Data -->
                        <tr class="student-row">
                            <td>1</td>
                            <td>
                                <div class="no-image">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                            </td>
                            <td>John Doe</td>
                            <td>ST001</td>
                            <td>Computer Science</td>
                            <td>john@example.com</td>
                            <td>+1 234 567 8900</td>
                            <td>123 Main St, City</td>
                            <td>
                                <div class="action-btns">
                                    <button class="edit-btn" onclick="editStudent(1)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="delete-btn" onclick="deleteStudent(1)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr class="student-row">
                            <td>2</td>
                            <td>
                                <div class="no-image">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                            </td>
                            <td>Jane Smith</td>
                            <td>ST002</td>
                            <td>Business Administration</td>
                            <td>jane@example.com</td>
                            <td>+1 234 567 8901</td>
                            <td>456 Oak Ave, Town</td>
                            <td>
                                <div class="action-btns">
                                    <button class="edit-btn" onclick="editStudent(2)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="delete-btn" onclick="deleteStudent(2)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Student Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Student</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="studentForm" onsubmit="saveStudent(event)">
                    <input type="hidden" id="studentId" name="student_id">
                    
                    <div class="form-group">
                        <label for="profileImageInput">Profile Photo</label>
                        <div class="file-upload-container">
                            <input type="file" id="profileImageInput" name="profile_image" class="file-upload-input" accept="image/*" onchange="handleFileSelect(event)">
                            <label for="profileImageInput" class="file-upload-btn">
                                <i class="fas fa-camera"></i> Choose Photo
                            </label>
                            <div class="file-preview" id="filePreview" style="display: none;">
                                <img id="previewImg" class="preview-image" src="" alt="Preview" onclick="viewModalImage()">
                                <div class="crop-container" id="cropContainer" style="display: none;">
                                    <div class="crop-area">
                                        <img id="cropImg" src="" alt="Crop">
                                    </div>
                                    <div style="margin-top: 10px; text-align: center;">
                                        <button type="button" class="btn-sm btn-save" onclick="cropImage()">
                                            <i class="fas fa-crop"></i> Crop Image
                                        </button>
                                        <button type="button" class="btn-sm btn-cancel" onclick="cancelCrop()">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="studentName">Full Name</label>
                            <input type="text" id="studentName" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="studentIdInput">Student ID</label>
                            <input type="text" id="studentIdInput" name="student_id_display" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="studentCourse">Course</label>
                            <select id="studentCourse" name="course" required>
                                <option value="">Select Course</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Business Administration">Business Administration</option>
                                <option value="Engineering">Engineering</option>
                                <option value="Medicine">Medicine</option>
                                <option value="Arts">Arts</option>
                                <option value="Science">Science</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="studentEmail">Email</label>
                            <input type="email" id="studentEmail" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="studentPhone">Phone</label>
                            <input type="tel" id="studentPhone" name="phone" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="studentAddress">Address</label>
                            <input type="text" id="studentAddress" name="address" required>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Viewer Modal -->
    <div id="imageViewerModal" class="image-viewer-modal">
        <div class="image-viewer-content">
            <span class="image-close">&times;</span>
            <img id="viewerImage" src="" alt="Full Size">
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
        let cropper = null;
        let croppedImageBlob = null;
        let students = [
            {
                id: 1,
                name: "John Doe",
                student_id: "ST001",
                course: "Computer Science",
                email: "john@example.com",
                phone: "+1 234 567 8900",
                address: "123 Main St, City",
                profile_image: null
            },
            {
                id: 2,
                name: "Jane Smith",
                student_id: "ST002",
                course: "Business Administration",
                email: "jane@example.com",
                phone: "+1 234 567 8901",
                address: "456 Oak Ave, Town",
                profile_image: null
            }
        ];
        let nextId = 3;

        // Modal Functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Student';
            document.getElementById('studentForm').reset();
            document.getElementById('studentId').value = '';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('cropContainer').style.display = 'none';
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            document.getElementById('studentModal').classList.add('show');
            document.getElementById('studentModal').style.display = 'block';
        }

        function closeModal() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            document.getElementById('studentModal').classList.remove('show');
            setTimeout(() => {
                document.getElementById('studentModal').style.display = 'none';
            }, 300);
        }

        function editStudent(id) {
            const student = students.find(s => s.id === id);
            if (student) {
                document.getElementById('modalTitle').textContent = 'Edit Student';
                document.getElementById('studentId').value = student.id;
                document.getElementById('studentName').value = student.name;
                document.getElementById('studentIdInput').value = student.student_id;
                document.getElementById('studentCourse').value = student.course;
                document.getElementById('studentEmail').value = student.email;
                document.getElementById('studentPhone').value = student.phone;
                document.getElementById('studentAddress').value = student.address;

                // Show existing image if available
                if (student.profile_image) {
                    const previewImg = document.getElementById('previewImg');
                    previewImg.src = student.profile_image;
                    document.getElementById('filePreview').style.display = 'block';
                    document.getElementById('cropContainer').style.display = 'none';
                } else {
                    document.getElementById('filePreview').style.display = 'none';
                }

                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }

                document.getElementById('studentModal').classList.add('show');
                document.getElementById('studentModal').style.display = 'block';
            }
        }

        function deleteStudent(id) {
            if (confirm('Are you sure you want to delete this student?')) {
                students = students.filter(s => s.id !== id);
                updateTable();
            }
        }

        // File Upload Functions
        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewImg = document.getElementById('previewImg');
                    const cropImg = document.getElementById('cropImg');
                    const filePreview = document.getElementById('filePreview');
                    const cropContainer = document.getElementById('cropContainer');
                    
                    previewImg.src = e.target.result;
                    cropImg.src = e.target.result;
                    filePreview.style.display = 'block';
                    cropContainer.style.display = 'block';
                    
                    // Destroy existing cropper if it exists
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    // Wait for image to load before initializing cropper
                    cropImg.onload = function() {
                        cropper = new Cropper(cropImg, {
                            aspectRatio: 1,
                            viewMode: 1,
                            autoCropArea: 0.8,
                            responsive: true,
                            restore: false,
                            guides: true,
                            center: true,
                            highlight: false,
                            cropBoxMovable: true,
                            cropBoxResizable: true,
                            toggleDragModeOnDblclick: false,
                            minContainerWidth: 250,
                            minContainerHeight: 180,
                            maxContainerWidth: 300,
                            maxContainerHeight: 200,
                        });
                    };
                };
                reader.readAsDataURL(file);
            }
        }

        function cropImage() {
            if (cropper) {
                const canvas = cropper.getCroppedCanvas({
                    width: 200,
                    height: 200,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                
                canvas.toBlob(function(blob) {
                    croppedImageBlob = blob;
                    const url = URL.createObjectURL(blob);
                    document.getElementById('previewImg').src = url;
                    document.getElementById('cropContainer').style.display = 'none';
                    
                    // Create a new file input with the cropped image
                    const dt = new DataTransfer();
                    const file = new File([blob], 'cropped_image.jpg', { type: 'image/jpeg' });
                    dt.items.add(file);
                    document.getElementById('profileImageInput').files = dt.files;
                }, 'image/jpeg', 0.9);
            }
        }

        function cancelCrop() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('profileImageInput').value = '';
        }

        function viewImage(imageSrc) {
            document.getElementById('viewerImage').src = imageSrc;
            document.getElementById('imageViewerModal').style.display = 'block';
        }

        function viewModalImage() {
            const previewImg = document.getElementById('previewImg');
            if (previewImg.src) {
                viewImage(previewImg.src);
            }
        }

        // Form Submission
        function saveStudent(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const studentId = formData.get('student_id');
            
            const studentData = {
                name: formData.get('name'),
                student_id: formData.get('student_id_display'),
                course: formData.get('course'),
                email: formData.get('email'),
                phone: formData.get('phone'),
                address: formData.get('address'),
                profile_image: document.getElementById('previewImg').src || null
            };

            if (studentId) {
                // Update existing student
                const index = students.findIndex(s => s.id == studentId);
                if (index !== -1) {
                    students[index] = { ...students[index], ...studentData };
                }
            } else {
                // Add new student
                studentData.id = nextId++;
                students.push(studentData);
            }

            updateTable();
            closeModal();
        }

        // Update Table
        function updateTable() {
            const tbody = document.getElementById('studentTableBody');
            tbody.innerHTML = '';

            students.forEach((student, index) => {
                const row = document.createElement('tr');
                row.className = 'student-row';
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>
                        ${student.profile_image ? 
                            `<img src="${student.profile_image}" alt="Profile" class="profile-image" onclick="viewImage('${student.profile_image}')">` :
                            `<div class="no-image"><i class="fas fa-user-graduate"></i></div>`
                        }
                    </td>
                    <td>${student.name}</td>
                    <td>${student.student_id}</td>
                    <td>${student.course}</td>
                    <td>${student.email}</td>
                    <td>${student.phone}</td>
                    <td>${student.address}</td>
                    <td>
                        <div class="action-btns">
                            <button class="edit-btn" onclick="editStudent(${student.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="delete-btn" onclick="deleteStudent(${student.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking outside
            window.onclick = function(event) {
                const modal = document.getElementById('studentModal');
                const imageModal = document.getElementById('imageViewerModal');
                
                if (event.target === modal) {
                    closeModal();
                }
                if (event.target === imageModal) {
                    imageModal.style.display = 'none';
                }
            };

            // Close image viewer
            document.querySelector('.image-close').onclick = function() {
                document.getElementById('imageViewerModal').style.display = 'none';
            };
        });
    </script>
</body>
</html>