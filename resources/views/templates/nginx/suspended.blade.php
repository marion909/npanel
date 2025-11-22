<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Suspended / Domain Gesperrt</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 48px;
            text-align: center;
        }
        
        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }
        
        h1 {
            color: #1a202c;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        
        .subtitle {
            color: #4a5568;
            font-size: 18px;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        
        .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 32px 0;
        }
        
        .section {
            margin-bottom: 24px;
        }
        
        .section h2 {
            color: #2d3748;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .section p {
            color: #718096;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .contact-info {
            background: #f7fafc;
            border-radius: 8px;
            padding: 24px;
            margin-top: 24px;
        }
        
        .contact-info p {
            color: #4a5568;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .contact-info a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .contact-info a:hover {
            text-decoration: underline;
        }
        
        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            color: #a0aec0;
            font-size: 14px;
        }
        
        @media (max-width: 640px) {
            .container {
                padding: 32px 24px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .subtitle {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
        </div>
        
        <h1>Domain Gesperrt / Domain Suspended</h1>
        
        <p class="subtitle">
            Diese Domain wurde vorübergehend gesperrt.<br>
            This domain has been temporarily suspended.
        </p>
        
        <div class="divider"></div>
        
        <div class="section">
            <h2>🇩🇪 Deutsch</h2>
            <p>
                Diese Domain wurde aus administrativen oder abrechnungstechnischen Gründen gesperrt.
                Wenn Sie der Eigentümer dieser Domain sind, kontaktieren Sie bitte Ihren Hosting-Provider,
                um diese Angelegenheit zu klären und den Zugriff wiederherzustellen.
            </p>
        </div>
        
        <div class="section">
            <h2>🇬🇧 English</h2>
            <p>
                This domain has been suspended for administrative or billing reasons.
                If you are the owner of this domain, please contact your hosting provider
                to resolve this matter and restore access.
            </p>
        </div>
        
        <div class="contact-info">
            <p><strong>Benötigen Sie Hilfe? / Need Help?</strong></p>
            <p>
                Kontaktieren Sie den Support / Contact Support<br>
                <a href="mailto:support@{{ config('app.name', 'nPanel') }}.com">support@{{ config('app.name', 'nPanel') }}.com</a>
            </p>
        </div>
        
        <div class="footer">
            Powered by {{ config('app.name', 'nPanel') }}
        </div>
    </div>
</body>
</html>
