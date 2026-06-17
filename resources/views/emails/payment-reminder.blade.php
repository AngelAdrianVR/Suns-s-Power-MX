<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de Pago</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 24px;
        }
        .card {
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #1e3a5f 0%, #0d8abc 100%);
            padding: 32px 40px;
            text-align: center;
        }
        .header img {
            max-width: 120px;
            margin-bottom: 12px;
        }
        .header h1 {
            color: #ffffff;
            font-size: 20px;
            margin: 0;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .body-content {
            padding: 32px 40px;
        }
        .body-content p {
            color: #374151;
            font-size: 15px;
            line-height: 1.6;
            margin: 0 0 16px 0;
        }
        .alert-box {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        .alert-box h3 {
            color: #92400e;
            font-size: 16px;
            margin: 0 0 12px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #fde68a;
            font-size: 14px;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #92400e;
            font-weight: 600;
        }
        .detail-value {
            color: #78350f;
            font-weight: 700;
        }
        .detail-value.overdue {
            color: #dc2626;
        }
        .message-box {
            background-color: #f9fafb;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            white-space: pre-wrap;
            font-size: 14px;
            color: #4b5563;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            background-color: #0d8abc;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 14px;
            margin: 8px 4px;
        }
        .btn-warning {
            background-color: #f59e0b;
        }
        .footer {
            padding: 24px 40px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            color: #9ca3af;
            font-size: 12px;
            margin: 4px 0;
        }
        .footer a {
            color: #0d8abc;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            .container { padding: 12px; }
            .header { padding: 24px 20px; }
            .body-content { padding: 24px 20px; }
            .footer { padding: 16px 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <!-- Header con Logo -->
            <div class="header">
                <img src="https://erp-spmx.com/images/isologo-suns-power-mx.png"
                     alt="Sun's Power MX"
                     onerror="this.style.display='none'"
                     style="max-width: 120px; height: auto;">
                <h1>Recordatorio de Pago</h1>
            </div>

            <!-- Cuerpo -->
            <div class="body-content">
                <p>Estimado(a) <strong>{{ $serviceOrder->client->name }}</strong>,</p>

                <p>Le recordamos que tiene un pago pendiente con <strong>Sun's Power MX</strong>.
                   A continuación los detalles de la mensualidad a pagar:</p>

                @if (!empty($installmentData))
                <div class="alert-box">
                    <h3>📋 Detalles del Pago</h3>
                    <div class="detail-row">
                        <span class="detail-label">Concepto</span>
                        <span class="detail-value">{{ $installmentData['label'] ?? 'Mensualidad' }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Monto</span>
                        <span class="detail-value">${{ number_format($installmentData['amount'] ?? 0, 2) }} MXN</span>
                    </div>
                    @if (!empty($installmentData['projected_date']))
                    <div class="detail-row">
                        <span class="detail-label">Fecha esperada</span>
                        <span class="detail-value {{ ($installmentData['status'] ?? '') === 'defaulted' ? 'overdue' : '' }}">
                            {{ \Carbon\Carbon::parse($installmentData['projected_date'])->format('d/m/Y') }}
                            @if (($installmentData['status'] ?? '') === 'defaulted' || ($installmentData['status'] ?? '') === 'late')
                                — VENCIDO
                            @endif
                        </span>
                    </div>
                    @endif
                    @if (!empty($installmentData['days_since_projected']) && $installmentData['days_since_projected'] > 0)
                    <div class="detail-row">
                        <span class="detail-label">Días de retraso</span>
                        <span class="detail-value overdue">{{ $installmentData['days_since_projected'] }} día(s)</span>
                    </div>
                    @endif
                </div>
                @endif

                <div class="message-box">
                    {{ $reminderMessage }}
                </div>

                <p style="text-align: center; margin-top: 24px;">
                    Si ya realizó su pago, por favor haga caso omiso a este mensaje.
                </p>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p><strong>Sun's Power MX</strong></p>
                <p>Expertos en Energía Solar</p>
                <p style="margin-top: 8px;">
                    <a href="mailto:notificaciones@erp-spmx.com">notificaciones@erp-spmx.com</a>
                </p>
                <p style="margin-top: 12px; font-size: 11px; color: #d1d5db;">
                    Este correo fue generado automáticamente por el sistema ERP de Sun's Power MX.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
