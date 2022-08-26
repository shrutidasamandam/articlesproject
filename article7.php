
<?php
session_start();

$loggedIn = false;

if (isset($_SESSION['loggedIn']) && isset($_SESSION['name'])) {
    $loggedIn = true;
}

$conn = new mysqli('localhost','root','dvsrikanth','commentsystem',4306);

function createCommentRow($data) {
    global $conn;

    $response = '
            <div class="comment">
                <div class="user">'.$data['name'].' <span class="time">'.$data['createdOn'].'</span></div>
                <div class="userComment">'.$data['comment'].'</div>
                <div class="reply"><a href="javascript:void(0)" data-commentID="'.$data['id'].'" onclick="reply(this)">REPLY</a></div>
                <div class="replies">';

    $sql = $conn->query("SELECT replies.id, name, comment, DATE_FORMAT(replies.createdOn, '%Y-%m-%d') AS createdOn FROM replies INNER JOIN users ON replies.userID = users.id WHERE replies.commentID = '".$data['id']."' ORDER BY replies.id DESC LIMIT 1");
    while($dataR = $sql->fetch_assoc())
        $response .= createCommentRow($dataR);

    $response .= '
                        </div>
            </div>
        ';

    return $response;
}

if (isset($_POST['getAllComments'])) {
    $start = $conn->real_escape_string($_POST['start']);

    $response = "";
    $sql = $conn->query("SELECT comments.id, name, comment, DATE_FORMAT(comments.createdOn, '%Y-%m-%d') AS createdOn FROM comments INNER JOIN users ON comments.userID = users.id ORDER BY comments.id DESC LIMIT $start, 20");
    while($data = $sql->fetch_assoc())
        $response .= createCommentRow($data);

    exit($response);
}

if (isset($_POST['addComment'])) {
    $comment = $conn->real_escape_string($_POST['comment']);
    $isReply = $conn->real_escape_string($_POST['isReply']);
    $commentID = $conn->real_escape_string($_POST['commentID']);

    if(isset($_SESSION["userID"])){
        $userid = $_SESSION["userID"];
    }else{
        echo "<script>alert('Please Register First!');</script>";
        exit();
    }
    

    if ($isReply != 'false') {
        $conn->query("INSERT INTO replies (comment, commentID, userID, createdOn) VALUES ('$comment', '$commentID', '".$_SESSION['userID']."', NOW())");
        $sql = $conn->query("SELECT replies.id, name, comment, DATE_FORMAT(replies.createdOn, '%Y-%m-%d') AS createdOn FROM replies INNER JOIN users ON replies.userID = users.id ORDER BY replies.id DESC LIMIT 1");
    } else {
        $conn->query("INSERT INTO comments (userID, comment, createdOn) VALUES ('".$_SESSION['userID']."','$comment',NOW())");
        $sql = $conn->query("SELECT comments.id, name, comment, DATE_FORMAT(comments.createdOn, '%Y-%m-%d') AS createdOn FROM comments INNER JOIN users ON comments.userID = users.id ORDER BY comments.id DESC LIMIT 1");
    }
    $data = $sql->fetch_assoc();
    exit(createCommentRow($data));
}

if (isset($_POST['register'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sql = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($sql->num_rows > 0)
            exit('failedUserExists');
        else {
            $ePassword = password_hash($password, PASSWORD_BCRYPT);
            $conn->query("INSERT INTO users (name,email,password,createdOn) VALUES ('$name', '$email', '$ePassword', NOW())");

            $sql = $conn->query("SELECT id FROM users ORDER BY id DESC LIMIT 1");
            $data = $sql->fetch_assoc();

            $_SESSION['loggedIn'] = 1;
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $_SESSION['userID'] = $data['id'];

            exit('success');
        }
    } else
        exit('failedEmail');
}

if (isset($_POST['logIn'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sql = $conn->query("SELECT id, password, name FROM users WHERE email='$email'");
        if ($sql->num_rows == 0)
            exit('failed');
        else {
            $data = $sql->fetch_assoc();
            $passwordHash = $data['password'];

            if (password_verify($password, $passwordHash)) {
                $_SESSION['loggedIn'] = 1;
                $_SESSION['name'] = $data['name'];
                $_SESSION['email'] = $email;
                $_SESSION['userID'] = $data['id'];

                exit('success');
            } else
                exit('failed');
        }
    } else
        exit('failed');
}

$sqlNumComments = $conn->query("SELECT id FROM comments");
$numComments = $sqlNumComments->num_rows;

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>YouTube Comment System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <style type="text/css">
        .comment {
            margin-bottom: 20px;
        }

        .user {
            font-weight: bold;
            color: black;
        }

        .time, .reply {
            color: gray;
        }

        .userComment {
            color: #000;
        }

        .replies .comment {
            margin-top: 20px;

        }

        .replies {
            margin-left: 20px;
        }

        #registerModal input, #logInModal input {
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="modal" id="registerModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registration Form</h5>
            </div>
            <div class="modal-body">
                <input type="text" id="userName" class="form-control" placeholder="Your Name">
                <input type="email" id="userEmail" class="form-control" placeholder="Your Email">
                <input type="password" id="userPassword" class="form-control" placeholder="Password">
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="registerBtn">Register</button>
                <button class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="logInModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log In Form</h5>
            </div>
            <div class="modal-body">
                <input type="email" id="userLEmail" class="form-control" placeholder="Your Email">
                <input type="password" id="userLPassword" class="form-control" placeholder="Password">
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="loginBtn">Log In</button>
                <button class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="container" style="margin-top:50px;">
    <div class="row">
        <div class="col-md-12" align="right">
            <?php
            if (!$loggedIn)
                echo '
                        <button class="btn btn-primary" data-toggle="modal" data-target="#registerModal">Register</button>
                        <button class="btn btn-success" data-toggle="modal" data-target="#logInModal">Log In</button>
                ';
            else
                echo '
                    <a href="logout.php" class="btn btn-warning">Log Out</a>
                ';
            ?>
        </div>
    </div>
    <div class="row" style="margin-top: 20px;margin-bottom: 20px;">
        <div class="col-md-12" align="center">



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Best Hikes</title>
    
    <link rel="shortcut icon" href="favicon.png" type="image/x-icon">

    <link rel="stylesheet" href="articlestyle1.css">
</head>
<body>
    <header>
        <cover>
            <div style="background-color: orange;
background-size:100%;height:400px;padding-top:40px;
text-align:center;">
</div>
        </cover>
    </header>
    <main>
        <div>
            <h1>Best Hikes</h1>
            <p class="summary">A few easy tips and tricks to make your life fulfilling!</p>
            <span class="info"> By <a>Shruti Dasamandam</a> | Dec 12, 2021, 12:20pm IST</span>
        </div>
        <div class="social">
            <a href="#"><img src="icon-facebook.svg" alt="Facebook"></a>
            <a href="#"><img src="icon-twitter.svg" alt="Twitter">
            <a class="share" href="#">
                <img src="icon-share.svg" alt="Share">
                Share
            </a>
        </div>
        <div class="content">
            <p> <h3>1. Inca trail, Peru</h3>
                One of life's great thrills is descending from the Inca Trail into Machu Pichu at sunrise. The Inca Trail is a 42-kilometer (26-mile) walk that takes you up over two 13,000-foot passes and is not for the faint of heart.
The pathway follows the path taken by the Incas over 650 years ago, with most of the original stonework still visible. You'll pass through two Inca tunnels, the ancient ruin of Winay Wayna, and numerous waterfalls along the way.
This is a strenuous hike that takes four days to complete in inclement weather. It can be hot and humid, raining heavily, scorching hot, and even chilly at times. Make sure you're ready for any type of weather.
            </p>
            <figure>
                <img src="ebc.jpg">
            </figure>
                <p> <h3>2. Everest Base Camp, Nepal</h3>
                    The Everest Base Camp climb may possibly be described in two words: up and down (EBC). This 65-kilometer one-way track climbs a mountain ridge, descends to a river, and then climbs another mountain ridge on its way to base camp.
                    The trip takes 12 to 14 days on average and is strenuous, reaching a maximum elevation of 5,500 metres (18,044 feet). However, the majority of the trekking takes place between 3,500 and 4,500 metres (11,482 and 4,763 feet). The air is thin up here, making any exertion seem twice as difficult.
                    </p>
                    <p> <h3>3. Samaria Gorge, Greece</h3> 
                        The Samaria Gorge trek is one of Crete's top attractions and the best hike on the island. This one-way trip begins in pine woodlands and descends through a small valley before reaching the Libyan Sea. The trip is little over 16 kilometres long, with a total elevation gain of over 1,500 metres.
                        The first hour of the trip is the most difficult, as you descend a lengthy series of switchbacks before reaching the torrent that created this amazing gorge. The terrain levels out at this point, and the final 13 kilometres or so are a reasonably pleasant stroll.
                    </p>
                         <p> <h3>4. Cinque Terre Hike, Italy </h3>
                            The Cinque Terre hike is an easy day hike between settlements located on the steep seaside mountains that has long been regarded as one of Italy's most picturesque climbs. The brilliant blue waters of the Mediterranean Sea below you will make you forget about the work you put in while strolling along this old road.
                            Starting at Monterosso and ending in Riomaggiore is the finest route to undertake the Cinque Terre trip. Take your time and stop for a coffee and biscotti in the middle of the morning, then continue walking before stopping for a delicious lunch. Take a break from your lunch spot's view and continue walking till you feel the need for an afternoon cappuccino.
                        </p>
            <figure>
                <img src="grancan.jpg">
            </figure>
            <p> <h3>5. Bright Angel Trail, Grand Canyon NP, USA
            </h3>
            A hiking trail winding down the cliffside across a large plain and then dropping down again towards the Colorado River may be seen when you gaze over the Grand Canyon rim. The Bright Angel Trail is a world-famous hiking trail.
            This 25-kilometer (15.6-mile) trail descends 1,340 metres (4,380 feet) from the south canyon rim to the water's edge at Phantom Ranch and Bright Angel Campground. The vistas along the trail across and down the canyon are breathtaking, and many people hike parts of it in a day. If this is your plan, the park service recommends staying no further than Indian Springs.
            </p>
            <hr>
        </div>
    </main>
    <div class="newsletter">
        <div class="wrapper">
            <div>
                <h2>Sign up for more!</h2>
                <p>Get our essential newsletters delivered everyday.</p>
            </div>
            <div>
                <form action="#">
                    <label for="Email">Email (required)</label>
                    <div class="">
                        <input type="email" name="" id="">
                        <input type="button" value="Subscribe">
                    </div>
                </form>
            </div>
        </div>
    </div>
    <footer>
        <div class="wrapper flex">
            <img class="logo" src="logo.png" alt="Logo">
            <img class="logo2" src="logo2.jpg" alt="Healthnerds media">
        </div>
    </footer>
</body>
</html>

</div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <textarea class="form-control" id="mainComment" placeholder="Add Public Comment" cols="30" rows="2"></textarea><br>
            <button style="float:right" class="btn-primary btn" onclick="isReply = false;" id="addComment">Add Comment</button>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <h2><b id="numComments"><?php echo $numComments ?> Comments</b></h2>
            <div class="userComments">

            </div>
        </div>
    </div>
</div>

<div class="row replyRow" style="display:none">
    <div class="col-md-12">
        <textarea class="form-control" id="replyComment" placeholder="Add Public Comment" cols="30" rows="2"></textarea><br>
        <button style="float:right" class="btn-primary btn" onclick="isReply = true;" id="addReply">Add Reply</button>
        <button style="float:right" class="btn-default btn" onclick="$('.replyRow').hide();">Close</button>
    </div>
</div>

<script src="http://code.jquery.com/jquery-3.4.1.min.js" integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
<script type="text/javascript">
    var isReply = false, commentID = 0, max = <?php echo $numComments ?>;

    $(name)

    $(document).ready(function () {
        $("#addComment, #addReply").on('click', function () {
            var comment;

            if (!isReply)
                comment = $("#mainComment").val();
            else
                comment = $("#replyComment").val();

            if (comment.length > 5) {
                $.ajax({
                    url: 'indexplay.php',
                    method: 'POST',
                    dataType: 'text',
                    data: {
                        addComment: 1,
                        comment: comment,
                        isReply: isReply,
                        commentID: commentID
                    }, success: function (response) {
                        max++;
                        $("#numComments").text(max + " Comments");
                        if (!isReply) {
                            $(".userComments").prepend(response);
                            $("#mainComment").val("");
                        } else {
                            commentID = 0;
                            $("#replyComment").val("");
                            $(".replyRow").hide();
                            $('.replyRow').parent().next().append(response);
                        }
                    }
                });
            } else
                alert('Please Check Your Inputs');
        });

        $("#registerBtn").on('click', function () {
            var name = $("#userName").val();
            var email = $("#userEmail").val();
            var password = $("#userPassword").val();

            if (name != "" && email != "" && password != "") {
                $.ajax({
                    url: 'indexplay.php',
                    method: 'POST',
                    dataType: 'text',
                    data: {
                        register: 1,
                        name: name,
                        email: email,
                        password: password
                    }, success: function (response) {
                        if (response === 'failedEmail')
                            alert('Please insert valid email address!');
                        else if (response === 'failedUserExists')
                            alert('User with this email already exists!');
                        else
                            window.location = window.location;
                    }
                });
            } else
                alert('Please Check Your Inputs');
        });

        $("#loginBtn").on('click', function () {
            var email = $("#userLEmail").val();
            var password = $("#userLPassword").val();

            if (email != "" && password != "") {
                $.ajax({
                    url: 'indexplay.php',
                    method: 'POST',
                    dataType: 'text',
                    data: {
                        logIn: 1,
                        email: email,
                        password: password
                    }, success: function (response) {
                        if (response === 'failed')
                            alert('Please check your login details!');
                        else
                            window.location = window.location;
                    }
                });
            } else
                alert('Please Check Your Inputs');
        });

        getAllComments(0, max);
    });



    function reply(caller) {
        commentID = $(caller).attr('data-commentID');
        $(".replyRow").insertAfter($(caller));
        $('.replyRow').show();
    }

    function getAllComments(start, max) {
        if (start > max) {
            return;
        }

        $.ajax({
            url: 'indexplay.php',
            method: 'POST',
            dataType: 'text',
            data: {
                getAllComments: 1,
                start: start
            }, success: function (response) {
                $(".userComments").append(response);
                getAllComments((start+20), max);
            }
        });
    }
</script>
</body>
</html>  
