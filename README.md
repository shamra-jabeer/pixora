\# Pixora



Pixora is an Instagram-inspired social media website where users can share photos, follow each other, and interact through likes, comments and messages. It is built with PHP and MySQL and runs locally on XAMPP.



\## Features



\- User registration, login and logout

\- Create posts with image uploads

\- Like and comment on posts

\- Follow and unfollow users

\- User profiles with profile editing

\- Direct messaging between users

\- Notifications for likes, comments and follows

\- Hashtags and hashtag search

\- Search, Discover and Trending pages

\- Account settings: change password, privacy options, delete account



\## Tech Stack



\- \*\*Frontend:\*\* HTML5, CSS3, JavaScript

\- \*\*Backend:\*\* PHP (MySQLi)

\- \*\*Database:\*\* MySQL

\- \*\*Server:\*\* Apache (XAMPP)



\## Project Structure



```

pixora/

├── assets/        # CSS, JavaScript and images

├── auth/          # Login, register and logout

├── config/        # Database connection

├── includes/      # Backend actions (likes, comments, follows, messages...)

├── uploads/       # User-uploaded posts and profile pictures

├── pixora\_db.sql  # Database structure

└── \*.php          # Main pages (home, profile, post, chat, settings...)

```



\## How to Run Locally



1\. Install XAMPP and start \*\*Apache\*\* and \*\*MySQL\*\*.

2\. Copy the `pixora` folder into `C:\\xampp\\htdocs\\`.

3\. Open `http://localhost/phpmyadmin` and create a database named `pixora\_db`.

4\. Select `pixora\_db`, click \*\*Import\*\*, choose `pixora\_db.sql`, and click \*\*Import\*\*.

5\. Check `config/database.php`. The defaults are:

&#x20;  - Host: `localhost`

&#x20;  - Username: `root`

&#x20;  - Password: \*(empty)\*

&#x20;  - Database: `pixora\_db`

6\. Open `http://localhost/pixora` in your browser.

