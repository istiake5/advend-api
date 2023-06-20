<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Password Reset</title>
  </head>
  <body style=" background-color: #F2F2F2;
  font-family: Arial, sans-serif;
  font-size: 16px;
  line-height: 1.4;
  color: #333333;">
    <div class="container" style=" max-width: 600px;
    margin: 0 auto;
    padding: 40px;
    background-color: #FFFFFF;
    border-radius: 5px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);">
      <h1 style="  font-size: 24px;
      margin: 0 0 20px;
      text-align: center;">Password Reset</h1>
      <p style=" margin: 0 0 20px;">Dear {{ $data['username'] }},</p>
      <p style=" margin: 0 0 20px;">We received a request to reset your password. If you did not make this request, please ignore this email.</p>
      <p style=" margin: 0 0 20px;">Please copy and paste the following Code</p>
      <p style=" margin: 0 0 20px;">{{ $data['confirm_code']}} </p>
      <p style=" margin: 0 0 20px;">Please note that this code is only valid for 24 hours.</p>
      <p style=" margin: 0 0 20px;">Thank you,</p>
      <p style=" margin: 0 0 20px;">The Advend Team</p>
    </div>
  </body>
</html> 