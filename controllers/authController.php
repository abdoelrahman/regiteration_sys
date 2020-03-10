<?php
require_once 'config/db.php';
require_once 'emailController.php';


$username = "";
$email = "";
$errors = [];

// SIGN UP USER
if (isset($_POST['signup-btn'])) {
    if (empty($_POST['username'])) {
        $errors['username'] = 'Username required';
    }
    if (empty($_POST['email'])) {
        $errors['email'] = 'Email required';
    }
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = "Invalid email address";
    }
    if (empty($_POST['password'])) {
        $errors['password'] = 'Password required';
    }
    if (isset($_POST['password']) && $_POST['password'] !== $_POST['passwordConf']) {
        $errors['passwordConf'] = 'The two passwords do not match';
    }

    $username = $_POST['username'];
    $email = $_POST['email'];
    $token = bin2hex(random_bytes(50)); // generate unique token
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); //encrypt password

    // Check if email already exists
    $sql = "SELECT * FROM users WHERE email='$email' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $errors['email'] = "Email already exists";
    }

    if (count($errors) === 0) {
        $query = "INSERT INTO users SET username=?, email=?, token=?, password=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssss', $username, $email, $token, $password);
        $result = $stmt->execute();

        if ($result) {
            $user_id = $stmt->insert_id;
            $stmt->close();

            sendVerificationEmail($email, $token);

            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['verified'] = false;
            $_SESSION['message'] = 'You are logged in!';
            $_SESSION['type'] = 'alert-success';
            header('location: index.php');
        } else {
            $_SESSION['error_msg'] = "Database error: Could not register user";
        }
    }
}

// LOGIN
if (isset($_POST['login-btn'])) {
    if (empty($_POST['username'])) {
        $errors['username'] = 'Username or email required';
    }
    if (empty($_POST['password'])) {
        $errors['password'] = 'Password required';
    }
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (count($errors) === 0) {
        $query = "SELECT * FROM users WHERE username=? OR email=? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $username, $username);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) { // if password matches
                $stmt->close();

                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['verified'] = $user['verified'];
                $_SESSION['message'] = 'You are logged in!';
                $_SESSION['type'] = 'alert-success';
                header('location: index.php');
                exit(0);
            } else { // if password does not match
                $errors['login_fail'] = "Wrong username / password";
            }
        } else {
            $_SESSION['message'] = "Database error. Login failed!";
            $_SESSION['type'] = "alert-danger";
        }
    }

}

if (isset($_GET['logout'])) {
    session_destroy();
    unset($_SESSION['username']);
    unset($_SESSION['email']);
    unset($_SESSION['verify']);
    header("location: login.php");
    exit(0);
}
if(isset($_POST['forget-password'])){
  $email=$_POST['email'];
  if (empty($_POST['email'])) {
      $errors['email'] = 'Email required';
  }
  if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = "Invalid email address";
  }
  if (count($errors) ===0) {
    $sql ="SELECT * FROM users WHERE email='$email' LIMIT 1";
    $result=mysqli_query($conn,$sql);
    $user=mysqli_fetch_assoc($result);
    $token =$user['token'];
    sendPasswordRestLink($email,$token);
    header('location: password_message.php');
    exit(0);
  }

}

// if click in reset password button
if (isset($_POST['reset-password-btn'])) {
  $password=$_POST['password'];
  $passwordConf=$_POST['passwordConf'];
  if (empty($_POST['password'] || empty($passwordConf))) {
      $errors['password'] = 'Password required';
  }
  if (isset($_POST['password']) && $_POST['password'] !== $_POST['passwordConf']) {
      $errors['passwordConf'] = 'The two passwords do not match';
  }
  $password = password_hash($password,PASSWORD_DEFAULT);
  $email=$_SESSION['email'];
  if (count($errors)==0) {
    $sql="UPDATE users SET password='password'WHERE email='email'";
    $result =mysqli_query($conn,$sql);
    if ($result) {
      header('location: login.php');
      exit(0);
    }
  }

}

function resetPassword($token){
  global $conn;
  $sql="SELECT * FROM users WHERE token='$token' LIMIT 1";
  $result =mysqli_query($conn,$sql);
  $user= mysqli_fetch_assoc($result);
  $_SESSION['email']=$user['email'];
  header('location: reset_password.php');
  exit(0);
}
