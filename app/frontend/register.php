<?php
if ($isLoggedIn) {
    redirect();
}

$currentPage["title"] = "Register";
require_once "../app/frontend/header.php";

if (isset($_GET["id"]) && isset($_GET["token"])) {
    $id = $_GET["id"];
    $token = $_GET["token"];

    $user = queryDB("SELECT email_token FROM users WHERE id=? AND email_token=?", [$id, $token])->fetch();

    if ($isLoggedIn && $token === $user["email_token"]) {
        $success = queryDB("UPDATE users SET email_token='' WHERE id=?", $id);

        if ($success) {
            addSuccess("Your email has been confirmed, you can now log in.");
            redirect(["p" => "login"]);
        }
        else {
            addError("There has been an error confirming the email.");
        }
    }
    else {
        addError("Can not confirm the user.");
    }
}

// --------------------------------------------------

$newUser = [
    "name" => "",
    "email" => ""
];

if ($action === null) {
    if (isset($_POST["register_name"])) {
        $newUser["name"] = $_POST["register_name"];
        $newUser["email"] = $_POST["register_email"];
        $newUser["password"] = $_POST["register_password"];
        $newUser["password_confirm"] = $_POST["register_password_confirm"];

        $recaptchaOK = true;
        if ($useRecaptcha) {
            $recaptchaOK = verifyRecaptcha($_POST["g-recaptcha-response"]);
        }

        if ($recaptchaOK && checkNewUserData($newUser)) {
            $role = "commenter";
            $user = queryDB("SELECT * FROM users")->fetch();
            if ($user === false) { // the first user gets to be admin
                $role = "admin";
            }

            $emailToken = md5(microtime(true)+mt_rand());

            $success = queryDB(
                'INSERT INTO users(name, email, email_token, password_hash, role, creation_date) VALUES(:name, :email, :email_token, :password_hash, :role, :creation_date)',
                [
                    "name" => $newUser["name"],
                    "email" => $newUser["email"],
                    "email_token" => $emailToken,
                    "password_hash" => password_hash($newUser['password'], PASSWORD_DEFAULT),
                    "role" => $role,
                    "creation_date" => date("Y-m-d")
                ]
            );

            if ($success) {
                sendConfirmEmail($email, $newUser["id"], $newUser["email_token"]);
                addSuccess("You have successfully been registered. You need to activate your account by clicking the link that has been sent to your email address");
            }
            else {
                addError("There was an error regsitering the user.");
            }
        }
        elseif (! $recaptchaOK) {
            addError("Please fill the captcha before submitting the form.");
        }
    }

    $link = "?p=register&a=resendconfirmation";
    if ($config["use_url_rewrite"] === 1) {
        $link = "register/resendconfirmation";
    }
?>

<h1>Register</h1>

<?php include "../app/messages.php"; ?>

<form action="?q=register" method="POST">
    <label>Name : <input type="text" name="register_name" value="<?php echo $newUser['name']; ?>" required></label> <br>
    <label>Email : <input type="email" name="register_email" value="<?php echo $newUser['email']; ?>" required></label> <br>
    <label>Password : <input type="password" name="register_password" required></label> <br>
    <label>Verify Password : <input type="password" name="register_password_confirm" required></label> <br>
<?php
if ($useRecaptcha) {
    require "../app/recaptchaWidget.php";
}
?>
    <input type="submit" value="Register">
</form>

<p>
    I want to <a href="<?php echo $link; ?>">receive the confirmation email</a> again.
</p>

<?php
}

// --------------------------------------------------
// resend confirm email

elseif ($action === "resendconfirmation") {
    if (isset($_POST["confirm_email"])) {
        $email = $_POST["confirm_email"];

        $recaptchaOK = true;
        if ($useRecaptcha) {
            $recaptchaOK = verifyRecaptcha($_POST["g-recaptcha-response"]);
        }

        if ($recaptchaOK && checkEmailFormat($email)) {
            $ok = true;

            $user = queryDB("SELECT id, email_token FROM users WHERE email=?", $email)->fetch();
            if ($user === false) {
                addError("No user with that email");
                $ok = false;
            }

            if ($user["email_token"] === "") {
                addError("No need to resend the confirmation email.");
                $ok = false;
            }

            if ($ok) {
                sendConfirmEmail($email, $user["id"], $user["email_token"]);
                addSuccess("Confirmation email has been sent again.");
            }
        }
        elseif (! $recaptchaOK) {
            addError("Please fill the captcha before submitting the form.");
        }
    }

?>

<h2>Send confirmation email again</h2>

<?php include "../app/messages.php"; ?>

<p>Fill the form below so that yu can receive the confirmation email again.</p>
<form action="?q=register" method="POST">
    <label>Email : <input type="email" name="confirm_email" required></label> <br>
    <input type="submit" value="Resend the email">
</form>

<?php
}
else {
    redirect();
}
