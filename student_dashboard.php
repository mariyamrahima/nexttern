<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}

$row = $result->fetch_assoc();
$student_id = htmlspecialchars($row['student_id']);
$first_name = htmlspecialchars($row['first_name']);
$last_name  = htmlspecialchars($row['last_name']);
$contact    = htmlspecialchars($row['contact']);
$gender     = htmlspecialchars($row['gender']);
$dob        = htmlspecialchars(date('d M Y', strtotime($row['dob'])));
$first_letter = strtoupper(substr($first_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard | Nexttern</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
:root{
  --nav-h:50px;        /* navbar height */
  --nav-gap:20px;      /* gap below navbar */
}

*{box-sizing:border-box;margin:0;padding:0;}

body{
  font-family:'Segoe UI',sans-serif;
  background:#f5fbfa;
  height:100vh;
  margin:0;
  display:flex;
  flex-direction:column;
  align-items:center;
  overflow:hidden;
  position:relative;
}

/* ===== NAVBAR ===== */
.navbar{
  position:fixed;
  top:0;left:0;
  width:100%;
  height:var(--nav-h);
  background:rgba(255,255,255,0.2);
  backdrop-filter:blur(12px);
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:0 20px;
  box-shadow:0 4px 20px rgba(0,0,0,0.1);
  z-index:100;
}
.nav-left img{height:40px;}

.nav-right{display:flex;align-items:center;gap:15px;}
.nav-right i{
  font-size:20px;
  color:#2e3944;
  cursor:pointer;
  transition:transform 0.2s ease,color 0.2s ease;
}
.nav-right i:hover{color:#035946;transform:scale(1.1);}

.nav-profile{
  width:34px;height:34px;
  border-radius:50%;
  background:#035946;
  color:#fff;
  display:flex;
  justify-content:center;
  align-items:center;
  font-weight:bold;
  font-size:15px;
  cursor:pointer;
  overflow:hidden;
  position:relative;
}
.nav-profile img{
  width:100%;height:100%;
  object-fit:cover;
  display:none;
}
.nav-profile span{pointer-events:none;}

/* notification dropdown */
.notif-wrapper{position:relative;}
.notif-dropdown{
  display:none;
  position:absolute;
  top:28px;right:0;
  background:rgba(255,255,255,0.85);
  backdrop-filter:blur(10px);
  border-radius:8px;
  padding:10px;
  font-size:14px;
  color:#2e3944;
  box-shadow:0 4px 10px rgba(0,0,0,0.15);
  min-width:200px;
  z-index:200;
}
.notif-dropdown p{text-align:center;margin:0;}

/* WRAPPER FOR DASHBOARD */
.dashboard-wrapper {
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding-top: 70px;
  box-sizing: border-box;
  height: calc(100vh - 50px);
}

/* DASHBOARD BOX */
.dashboard-box {
  display: flex;
  width: 90%;
  max-width: 1200px;
  min-height: calc(100vh - 90px);
  background: rgba(255, 255, 255, 0.25);
  backdrop-filter: blur(14px);
  border-radius: 20px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
  border: 1px solid rgba(255,255,255,0.3);
  overflow: hidden;
  margin: 0 auto;
  animation: dashboardIn 0.8s ease forwards;
}
@keyframes fadeInBox{
  from{opacity:0;}
  to{opacity:1;}
}

/* ===== SIDEBAR ===== */
.sidebar{
  width:250px;
  background:rgba(3,89,70,0.07);
  display:flex;
  flex-direction:column;
  padding:20px;
  justify-content:space-between;
}
.sidebar-top{
  display:flex;
  flex-direction:column;
  align-items:center;
  margin-bottom:20px;
  position:relative;
}
.sidebar-avatar{
  width:100px;height:100px;
  border-radius:50%;
  border:3px solid #035946;
  overflow:hidden;
  display:flex;
  justify-content:center;
  align-items:center;
  background:#d9e7e4;
  font-size:32px;
  font-weight:bold;
  color:#035946;
  cursor:pointer;
  transition:transform 0.3s;
  position:relative;
}
.sidebar-avatar:hover{transform:scale(1.05);}
.sidebar-avatar img{
  width:100%;height:100%;
  object-fit:cover;
  display:none;
}
.avatar-letter{position:absolute;z-index:2;pointer-events:none;}
.profile-id{
  margin-top:10px;
  font-weight:bold;
  color:#2e3944;
  font-size:14px;
}

.menu-section{display:flex;flex-direction:column;}
.menu-btn{
  background:#fff;
  border:none;
  padding:6px 10px;
  border-radius:8px;
  margin-bottom:8px;
  font-size:13px;
  cursor:pointer;
  color:#035946;
  display:flex;
  align-items:center;
  transition:all 0.3s ease;
}
.menu-btn i{margin-right:6px;font-size:14px;}
.menu-btn:hover,
.menu-btn.active{
  background:#035946;
  color:#fff;
  transform:scale(1.05);
}

.logout-btn{
  background-color:#035946;
  color:#fff;
  border:none;
  padding:10px;
  font-size:14px;
  border-radius:8px;
  cursor:pointer;
  transition:all 0.3s ease;
}
.logout-btn:hover{background-color:#024437;transform:scale(1.05);}

/* ===== CONTENT ===== */
.content{
  flex:1;
  padding:40px;
  text-align:center;
  height:100%;
  overflow-y:auto;
}
.tab-content{display:none;animation:contentFade 0.3s ease;}
.tab-content.active{display:block;}
@keyframes contentFade{
  from{opacity:0;transform:translateY(5px);}
  to{opacity:1;transform:translateY(0);}
}

.profile-header-greeting{
  font-size:32px;
  font-weight:bold;
  color:#035946;
  margin-bottom:10px;
}
.profile-divider{
  border:none;
  height:2px;
  background:rgba(3,89,70,0.25);
  margin:20px auto 30px auto;
  width:60%;
}

.profile-form-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
  gap:20px 40px;
  max-width:800px;
  margin:0 auto;
  text-align:left;
}
.profile-form-grid .form-group label{
  display:block;
  font-size:14px;
  font-weight:bold;
  color:#035946;
  margin-bottom:6px;
}
.profile-form-grid .form-group input,
.profile-form-grid .form-group select{
  width:100%;
  padding:10px 12px;
  border:1px solid #ccc;
  border-radius:8px;
  font-size:15px;
  background:#f9f9f9;
  color:#333;
}
.hidden { display: none; }

.profile-buttons{
  margin-top:25px;
  display:flex;
  justify-content:center;
  gap:15px;
}
.profile-buttons button{
  padding:8px 18px;
  font-size:14px;
  border:none;
  border-radius:8px;
  cursor:pointer;
  transition:transform 0.3s,background 0.3s;
}
.profile-buttons button:hover{transform:scale(1.05);}
.edit-btn{background:#035946;color:#fff;}
.save-btn{background:#2e3944;color:#fff;display:none;}
.cancel-btn{background:#035946;color:#fff;display:none;}

/* ===== BLOBS ===== */
.blob{position:absolute;border-radius:50%;z-index:0;animation:moveBlob 20s infinite alternate ease-in-out;}
.blob1{width:500px;height:500px;background:rgba(3,89,70,0.15);top:-100px;right:-150px;}
.blob2{width:300px;height:300px;background:rgba(3,89,70,0.2);top:150px;right:-100px;animation-delay:2s;}
.blob3{width:250px;height:250px;background:rgba(3,89,70,0.12);bottom:50px;left:-120px;animation-delay:4s;}
.blob4{width:150px;height:150px;background:rgba(3,89,70,0.18);bottom:-60px;left:80px;animation-delay:1s;}
@keyframes moveBlob{
  0%{transform:translate(0,0)scale(1);}
  50%{transform:translate(20px,-20px)scale(1.05);}
  100%{transform:translate(-20px,20px)scale(1);}
}

@media(max-width:768px){
  .dashboard-wrapper{
    height:auto;
    margin-top:calc(var(--nav-h) + var(--nav-gap));
  }
  .dashboard-box{
    flex-direction:column;
    height:auto;
  }
  .sidebar{
    width:100%;
    flex-direction:row;
    overflow-x:auto;
    padding:10px;
    align-items:center;
  }
  .sidebar-top{margin-bottom:0;margin-right:20px;}
  .content{padding:20px;height:auto;overflow-y:visible;}
  .profile-header-greeting{font-size:26px;}
}
</style>
</head>
<body>

<!-- BLOBS -->
<div class="blob blob1"></div>
<div class="blob blob2"></div>
<div class="blob blob3"></div>
<div class="blob blob4"></div>

<!-- NAVBAR -->
<div class="navbar">
  <div class="nav-left">
    <img src="logo.png" alt="Nexttern Logo">
  </div>
  <div class="nav-right">
    <div class="notif-wrapper">
      <i class="fas fa-bell notif-icon" onclick="toggleNotifications()"></i>
      <div class="notif-dropdown" id="notifDropdown">
        <p>No new notifications</p>
      </div>
    </div>
    <i class="fas fa-globe lang-icon" title="Change Language"></i>
   <div class="nav-profile" id="navProfile" title="Go to Dashboard" onclick="window.location.href='student_dashboard.php'">
      <img id="nav-profile-img" src="uploads/default-avatar.png" alt="Profile">
      <span id="nav-profile-letter"><?php echo $first_letter; ?></span>
    </div>
  </div>
</div>

<!-- DASHBOARD -->
<div class="dashboard-wrapper">
  <div class="dashboard-box">
    <!-- SIDEBAR -->
    <div class="sidebar">
      <div>
        <div class="sidebar-top">
          <div class="sidebar-avatar" id="sidebar-avatar" title="Click to change photo">
            <img id="sidebar-profile-img" src="uploads/default-avatar.png" alt="Profile">
            <span class="avatar-letter"><?php echo $first_letter; ?></span>
          </div>
          <div class="profile-id">ID: <?php echo $student_id; ?></div>
        </div>
        <div class="menu-section">
          <button class="menu-btn active" data-tab="profile" onclick="showTab('profile')"><i class="fas fa-user"></i>Profile</button>
          <button class="menu-btn" data-tab="internships" onclick="showTab('internships')"><i class="fas fa-briefcase"></i>Internships</button>
          <button class="menu-btn" data-tab="shortlisted" onclick="showTab('shortlisted')"><i class="fas fa-check-circle"></i>Shortlisted</button>
          <button class="menu-btn" data-tab="offers" onclick="showTab('offers')"><i class="fas fa-file-alt"></i>Offers</button>
          <button class="menu-btn" data-tab="messages" onclick="showTab('messages')"><i class="fas fa-envelope"></i>Messages</button>
        </div>
      </div>
      <form action="logout.php" method="post">
        <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
      </form>
    </div>

    <!-- MAIN CONTENT -->
    <div class="content">
      <div id="profile" class="tab-content active">
        <div class="profile-header-greeting">
          Hello, <?php echo strtoupper($first_name . ' ' . $last_name); ?>
        </div>
        <hr class="profile-divider">
        <form id="profile-form" class="profile-form-grid">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" value="<?php echo $first_name . ' ' . $last_name; ?>" readonly>
          </div>
          <div class="form-group">
            <label>Email (login)</label>
            <input type="email" name="email" value="<?php echo $email; ?>" readonly>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" value="<?php echo $contact; ?>" readonly>
          </div>
          <div class="form-group">
            <label>Gender</label>
            <input type="text" name="gender" value="<?php echo $gender; ?>" readonly>
          </div>
          <div class="form-group">
            <label>Date of Birth</label>
            <input type="text" name="dob" value="<?php echo $dob; ?>" readonly>
          </div>

          <!-- Education Qualification -->
          <div class="form-group">
            <label>Latest Education Qualification</label>
            <select name="education" id="education" onchange="togglePassoutYear()" disabled>
              <option value="">-- Select --</option>
              <option value="graduate">Graduate</option>
              <option value="postgraduate">Post Graduate</option>
              <option value="currently_studying">Currently Studying</option>
            </select>
          </div>

          <!-- Year of Passout -->
          <div class="form-group hidden" id="year-group">
            <label>Year of Passout</label>
            <input type="number" name="year_passout" id="year_passout" placeholder="e.g., 2026" min="1990" max="2050" readonly>
          </div>
        </form>
        <div class="profile-buttons">
          <button type="button" class="edit-btn" onclick="toggleEdit(false)">Edit</button>
          <button type="button" class="save-btn" onclick="saveProfile()">Save</button>
          <button type="button" class="cancel-btn" onclick="toggleEdit(true)">Cancel</button>
        </div>
      </div>

      <!-- OTHER TABS -->
      <div id="internships" class="tab-content"><h2>Internships</h2></div>
      <div id="shortlisted" class="tab-content"><h2>Shortlisted</h2></div>
      <div id="offers" class="tab-content"><h2>Offers</h2></div>
      <div id="messages" class="tab-content"><h2>Messages</h2></div>
    </div>
  </div>
</div>

<script>
function showTab(tabName){
  document.querySelectorAll(".tab-content").forEach(tab=>tab.classList.remove("active"));
  const target=document.getElementById(tabName);
  if(target){target.classList.add("active");}

  document.querySelectorAll(".menu-btn").forEach(btn=>btn.classList.remove("active"));
  const btn=document.querySelector("[data-tab='"+tabName+"']");
  if(btn){btn.classList.add("active");}
}

function toggleEdit(cancel){
  const inputs=document.querySelectorAll("#profile-form input, #profile-form select");
  const editBtn=document.querySelector(".edit-btn");
  const saveBtn=document.querySelector(".save-btn");
  const cancelBtn=document.querySelector(".cancel-btn");

  if(cancel){
    inputs.forEach(inp=>{
      if(inp.dataset.orig!==undefined){inp.value=inp.dataset.orig;}
      inp.readOnly=true;
      if(inp.tagName === "SELECT") inp.disabled = true;
    });
    editBtn.style.display="inline-block";
    saveBtn.style.display="none";
    cancelBtn.style.display="none";
    return;
  }

  inputs.forEach(inp=>{
    inp.dataset.orig=inp.value;
    if(inp.name!=="email"){
      inp.readOnly=false;
      if(inp.tagName === "SELECT") inp.disabled = false;
    }
  });
  editBtn.style.display="none";
  saveBtn.style.display="inline-block";
  cancelBtn.style.display="inline-block";
}

function saveProfile(){
  alert("Profile Saved (dummy alert).");
  toggleEdit(true);
}

function toggleNotifications(){
  const dropdown=document.getElementById("notifDropdown");
  dropdown.style.display=(dropdown.style.display==="block")?"none":"block";
}
window.addEventListener("click",function(e){
  if(!e.target.closest(".notif-wrapper")){
    document.getElementById("notifDropdown").style.display="none";
  }
});

function togglePassoutYear() {
  const educationSelect = document.getElementById("education").value;
  const yearGroup = document.getElementById("year-group");
  if (educationSelect === "currently_studying") {
    yearGroup.classList.remove("hidden");
  } else {
    yearGroup.classList.add("hidden");
  }
}

/* AVATAR UPLOAD */
document.getElementById("sidebar-avatar").addEventListener("click",()=>{
  const input=document.createElement("input");
  input.type="file";
  input.accept="image/*";
  input.onchange=e=>{
    const file=e.target.files[0];
    if(!file)return;
    const reader=new FileReader();
    reader.onload=function(ev){
      const src=ev.target.result;
      const sideImg=document.getElementById("sidebar-profile-img");
      sideImg.src=src;
      sideImg.style.display="block";
      document.querySelector(".sidebar-avatar .avatar-letter").style.display="none";
      const navImg=document.getElementById("nav-profile-img");
      const navLetter=document.getElementById("nav-profile-letter");
      navImg.src=src;
      navImg.style.display="block";
      navLetter.style.display="none";
    };
    reader.readAsDataURL(file);
  };
  input.click();
});
</script>
</body>
</html>