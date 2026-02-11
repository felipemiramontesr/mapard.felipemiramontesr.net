import os
import json
import logging
import unicodedata
from datetime import datetime
from typing import Optional

from state_manager import StateManager
from pdf_converter import PdfConverter

class ReportGenerator:
    """
    v89: IMPACT SECTIONS + DYNAMIC MITIGATION + SPANISH + PREMIUM STYLE.
    """
    def __init__(self, state_manager: Optional[StateManager] = None) -> None:
        """Initialize the Report Generator.
        
        Args:
            state_manager: Optional shared StateManager instance.
        """
        self.logger = logging.getLogger("ReportGenerator")
        logging.basicConfig(level=logging.INFO)
        
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        self.templates_dir = os.path.join(base_dir, '08_Templates')
        self.reports_dir = os.path.join(base_dir, '04_Data', 'reports')
        self.tracking_dir = os.path.join(base_dir, '04_Data', 'tracking')
        self.arco_root = os.path.join(base_dir, '04_Data', 'arco')
        
        self.ensure_dirs()
        self.pdf_converter = PdfConverter()
        self.state_manager = state_manager or StateManager()

    def ensure_dirs(self):
        for d in [self.reports_dir, self.arco_root, self.tracking_dir]:
            os.makedirs(d, exist_ok=True)

    def sanitize_filename(self, name: str) -> str:
        """Sanitize a string to be safe for filenames.
        
        Args:
            name: The string to sanitize.
            
        Returns:
            str: The sanitized string.
        """
        # Optimized: Normalize unicode characters to decompose accents (NFD),
        # filter non-spacing marks, and encode back to ASCII.
        nfkd_form = unicodedata.normalize('NFKD', name)
        only_ascii = "".join([c for c in nfkd_form if not unicodedata.combining(c)])
        return only_ascii.replace(" ", "_")

    def _build_report_name(self, file_type: str, client_id: str, client_name: str, report_id: str, date_str: str) -> str:
        """Construct a standardized report filename.
        
        Args:
            file_type: Prefix type (e.g., REPORTE).
            client_id: Client identifier.
            client_name: Full client name.
            report_id: Report identifier.
            date_str: Date string (YYYY-MM-DD).
            
        Returns:
            str: The constructed filename.
        """
        try:
            clean_date = datetime.strptime(date_str, "%Y-%m-%d").strftime("%d%m%Y")
        except:
            clean_date = date_str.replace("-", "")
        
        r_num = report_id.split('-')[-1] if '-' in report_id else "001"
        safe_name = self.sanitize_filename(client_name)
        return f"MAPA-RD_{client_id}_{safe_name}_{clean_date}_{r_num}"

    def generate_report(self, client_name: str, report_id: str, client_id: str, findings: list, arco_data: Optional[dict] = None, is_rescue: bool = False, report_type: str = "BASELINE") -> dict:
        """Generate the HTML report and return file paths.
        
        Args:
            client_name: Name of the client.
            report_id: Report ID.
            client_id: Client ID.
            findings: List of scored findings.
            arco_data: Optional data for ARCO rights.
            is_rescue: Whether this is a rescue report.
            report_type: Type of report (BASELINE, MONTHLY, etc).
            
        Returns:
            dict: Paths to generated artifacts ("md_path", "pdf_path").
        """
        self.logger.info(f"[*] Generating Report V89 (Impact & Timeline) for {client_name}...")
        nice_date = datetime.now().strftime("%Y-%m-%d")

        full_html = self._assemble_full_html(client_name, report_id, nice_date, findings)

        base_name = self._build_report_name("REPORTE", client_id, client_name, report_id, nice_date)
        html_path = os.path.join(self.reports_dir, f"{base_name}.html")
        
        with open(html_path, 'w', encoding='utf-8') as f:
            f.write(full_html)

        json_path = os.path.join(self.reports_dir, f"{base_name}.json")
        with open(json_path, 'w', encoding='utf-8') as f:
            json.dump(findings, f, indent=2)

        return {"md_path": html_path, "pdf_path": html_path}

    def _get_premium_style(self):
        return """
        /* --- PREMIUM V89: PIXEL PERFECT STYLE + IMPACT SECTIONS --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: radial-gradient(circle at top right, #1a234a 0%, #0a0e27 100%); color: #e8e8e8; min-height: 100vh; display: flex; align-items: center; flex-direction: column; padding: 3rem 1.25rem; }
        .container { width: 100%; max-width: 980px; text-align: center; }
        .hero-top { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; margin: 0 auto 2rem; }
        .lock-icon { display: block; width: 62px; height: 62px; color: #8a9fca; opacity: 0.9; filter: drop-shadow(0 0 10px rgba(138, 159, 202, 0.3)); }
        .tag { display: inline-flex; align-items: center; justify-content: center; padding: .55rem 1.15rem; border: 1px solid rgba(138, 159, 202, 0.2); background: rgba(138, 159, 202, 0.05); border-radius: 4px; font-size: .72rem; letter-spacing: .2em; color: #8a9fca; font-weight: 600; text-transform: uppercase; }
        h1 { font-size: 2rem; font-weight: 300; letter-spacing: -0.01em; margin-bottom: 1.2rem; color: #fff; line-height: 1.2; background: linear-gradient(180deg, #fff 0%, #a8adc7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .subtitle { font-size: 1.05rem; color: #a8adc7; font-weight: 300; margin: 0 auto 1.5rem; letter-spacing: -0.01em; line-height: 1.7; max-width: 760px; }
        .description { font-size: .95rem; color: #8892b4; max-width: 760px; margin: 0 auto 3rem; line-height: 1.8; font-weight: 300; }
        
        .section { text-align: left; margin-top: 4.0rem; padding-top: 3rem; border-top: 1px solid rgba(74, 85, 120, .15); }
        .section h2 { text-align:center; font-size: 1rem; font-weight: 600; color: #fff; letter-spacing: .15em; text-transform: uppercase; margin-bottom: 2rem; opacity: 0.8; }
        .grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
        
        /* CARD DESIGN */
        .card { 
            background: rgba(13, 17, 33, 0.6); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255, 255, 255, 0.08); 
            border-radius: 8px; 
            padding: 2rem; 
            text-align: left; 
            display: flex; 
            flex-direction: column; 
            position: relative; 
            overflow: hidden; 
        }
        
        /* Specific Risk Borders */
        .card.risk-critical { border: 1px solid rgba(163, 73, 255, 0.3); box-shadow: 0 0 20px rgba(163, 73, 255, 0.05); }
        .card.risk-high { border: 1px solid rgba(255, 42, 42, 0.3); box-shadow: 0 0 20px rgba(255, 42, 42, 0.05); }
        .card.risk-medium { border: 1px solid rgba(255, 184, 0, 0.3); box-shadow: 0 0 20px rgba(255, 184, 0, 0.05); }
        .card.risk-low { border: 1px solid rgba(0, 163, 255, 0.3); box-shadow: 0 0 20px rgba(0, 163, 255, 0.05); }

        /* Left Accent Line */
        .card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; }
        .card.risk-critical::before { background: #a349ff; box-shadow: 0 0 10px #a349ff; }
        .card.risk-high::before { background: #ff2a2a; box-shadow: 0 0 10px #ff2a2a; }
        .card.risk-medium::before { background: #ffb800; box-shadow: 0 0 10px #ffb800; }
        .card.risk-low::before { background: #00a3ff; box-shadow: 0 0 10px #00a3ff; }

        /* Top Right Icon */
        .card-icon { position: absolute; top: 2rem; right: 2rem; font-size: 1.5rem; }
        .risk-critical .card-icon { color: #a349ff; filter: drop-shadow(0 0 5px #a349ff); }
        .risk-high .card-icon { color: #ff2a2a; filter: drop-shadow(0 0 5px #ff2a2a); }
        .risk-medium .card-icon { color: #ffb800; filter: drop-shadow(0 0 5px #ffb800); }
        .risk-low .card-icon { color: #00a3ff; filter: drop-shadow(0 0 5px #00a3ff); }

        /* Header */
        .card h3 { font-size: 1.2rem; margin-bottom: 0.3rem; color: #fff; font-weight: 700; }
        .type-label { font-size: 0.85rem; color: #8892b4; display: block; margin-bottom: 2rem; }

        /* Highlight Box (Risk Justification) */
        .risk-box {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 4px;
            padding: 1rem 1.2rem;
            margin-bottom: 2rem;
            border-left-width: 3px;
            border-left-style: solid;
            font-size: 0.85rem;
        }
        .risk-critical .risk-box { border-left-color: #a349ff; color: #dcb3ff; }
        .risk-high .risk-box { border-left-color: #ff2a2a; color: #ff8080; }
        .risk-medium .risk-box { border-left-color: #ffb800; color: #ffdb80; }
        .risk-low .risk-box { border-left-color: #00a3ff; color: #80d1ff; }

        /* Labels */
        .label-kpi {
            font-size: 0.75rem;
            font-weight: 700;
            color: #5c6da0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            display: block;
        }

        .description-text {
            font-size: 0.9rem;
            line-height: 1.6;
            color: #a8adc7;
            margin-bottom: 2rem;
        }

        /* List Items */
        .mitigation-list { list-style: none; margin: 0; padding: 0; }
        .mitigation-list li {
            position: relative;
            padding-left: 1.5rem;
            margin-bottom: 1rem;
            color: #c5cae0;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .mitigation-list li::before {
            content: "→";
            position: absolute;
            left: 0;
            color: #5c6da0;
            font-weight: 400;
        }
        .mitigation-list li strong { color: #fff; font-weight: 600; margin-right: 4px; }

        /* --- MASTER SCORE CARD CSS --- */
        .master-score-card { background: rgba(10, 15, 30, 0.4); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 2.5rem 3rem; position: relative; box-shadow: 0 40px 100px rgba(0, 0, 0, 0.7); overflow: hidden; margin-bottom: 5rem; line-height: 1.6; }
        .master-score-header { text-align: center; margin-bottom: 3.5rem; }
        .main-score { font-size: 11rem; font-weight: 100 !important; margin: 3rem 0; letter-spacing: -10px; line-height: 0.7; text-shadow: 0 15px 70px rgba(255, 255, 255, 0.05); }
        .score-label { font-size: 0.95rem; font-weight: 700; color: #8a9fca; margin-bottom: 0.5rem; letter-spacing: 0.3em; text-transform: uppercase; }
        .thermometer-container { width: 100%; max-width: 850px; margin: 3.5rem auto; height: 14px; background: rgba(255, 255, 255, 0.05); border-radius: 100px; position: relative; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.5); }
        .thermometer-fill { height: 100%; border-radius: 100px; width: 0%; animation: thermometer-load 3s forwards ease-out; background: linear-gradient(90deg, #00ff85 0%, #00a3ff 20%, #ffb800 45%, #ff2a2a 75%, #a349ff 100%); background-size: 850px 14px; position: relative; }
        .thermometer-icon { position: absolute; top: 50%; right: -16px; transform: translate(0, -50%); width: 32px; height: 32px; background: #0f172a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 2px solid currentColor; box-shadow: 0 0 15px currentColor; z-index: 10; }
        
        .glossary-container { background: rgba(10, 15, 30, 0.6); border-radius: 12px; padding: 2.5rem; max-width: 850px; margin: 0 auto; border: 1px solid rgba(255, 255, 255, 0.05); text-align: center; }
        .glossary-title { color: #fff; font-size: 0.95rem; letter-spacing: 0.15em; text-transform: uppercase; font-weight: 700; margin-bottom: 1.5rem; display: block; }
        .glossary-desc { color: #8a9fca; font-size: 0.95rem; margin-bottom: 2.5rem; font-weight: 300; }
        .calc-box { background: rgba(0, 0, 0, 0.3); border-radius: 8px; padding: 2rem; font-family: 'Courier New', monospace; text-align: right; border: 1px solid rgba(255, 255, 255, 0.05); }
        .calc-row { display: flex; justify-content: space-between; margin-bottom: 0.8rem; font-size: 1rem; color: #8a9fca; }
        .calc-row strong { font-weight: 700; }
        .calc-last { border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 1rem; margin-top: 1rem; font-size: 1.2rem; align-items: center; }

        .text-critico { color: #ff7675; } .text-alto { color: #ff9f43; } .text-medio { color: #ffeaa7; } .text-bajo { color: #74b9ff; } .text-nulo { color: #a29bfe; }
        .color-cycle-alto { animation: cycle-alto 4s forwards; }
        @keyframes cycle-alto { 0% { color: #74b9ff; text-shadow: 0 0 20px rgba(116,185,255,0.4); } 100% { color: #ff9f43; text-shadow: 0 0 20px rgba(255,159,67,0.4); } }
        @keyframes thermometer-load { from { width: 0%; } to { width: var(--target-score); } }

        /* --- IMPACT SECTIONS CSS (V89) --- */
        .impact-card { background: rgba(255, 42, 42, 0.05); border: 1px solid rgba(255, 42, 42, 0.2); border-radius: 8px; padding: 2rem; text-align: center; margin-bottom: 2rem; }
        .price-tag { font-size: 3rem; font-weight: 800; color: #fff; text-shadow: 0 0 20px rgba(255, 42, 42, 0.5); display:block; margin: 1rem 0; }
        .impact-note { color: #ff8080; font-size: 0.9rem; }
        
        .market-table { width: 100%; border-collapse: collapse; margin-top: 1rem; color: #c5cae0; font-size: 0.9rem; }
        .market-table th { text-align: left; padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.1); color: #8a9fca; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.8rem; }
        .market-table td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .market-table tr:last-child td { border-bottom: none; }
        .market-highlight { color: #fff; font-weight: 600; }
        .market-price { color: #ff2a2a; font-family: 'Courier New', monospace; font-weight: 700; }
        
        .timeline { position: relative; max-width: 800px; margin: 3rem auto; padding-left: 2rem; border-left: 2px solid rgba(255,255,255,0.1); }
        .timeline-item { position: relative; margin-bottom: 2.5rem; padding-left: 1.5rem; }
        .timeline-item::before { content: ''; position: absolute; left: -2.6rem; top: 0.4rem; width: 14px; height: 14px; background: #111625; border: 2px solid #5c6da0; border-radius: 50%; }
        .timeline-date { font-size: 0.8em; letter-spacing: 0.1em; color: #5c6da0; text-transform: uppercase; margin-bottom: 0.5rem; display: block; }
        .timeline-title { color: #fff; font-weight: 600; font-size: 1.1rem; }
        
        .hacker-path { background: rgba(10, 15, 30, 0.6); padding: 2rem; border-radius: 12px; border: 1px dashed rgba(255, 255, 255, 0.1); max-width: 800px; margin: 0 auto; }
        .step { display:flex; gap: 1rem; margin-bottom: 1.5rem; align-items: flex-start; }
        .step-num { flex-shrink:0; width: 30px; height: 30px; background: #00a3ff; color: #000; font-weight: 800; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .step-text { color: #c5cae0; font-size: 0.95rem; line-height: 1.6; }
        
        @media (min-width: 901px) {
            .grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 24px; }
            .grid > * { grid-column: span 12; }
            .cols-2 > * { grid-column: span 6; }
            h1 { font-size: 3rem; }
        }
        """

    def _assemble_full_html(self, client_name, report_id, date, findings):
        # 0. Sort by Criticality
        risk_map = {'P0': 0, 'P1': 1, 'P2': 2, 'P3': 3}
        findings.sort(key=lambda x: risk_map.get(x.get('risk_score', 'P3'), 99))

        # 1. Logic (V79)
        scores = []
        for f in findings:
            p = f.get('risk_score', 'P3')
            if p == 'P0':   e, c, v = 100, 100, 100
            elif p == 'P1': e, c, v = 100, 80, 100
            elif p == 'P2': e, c, v = 50, 50, 100
            else:           e, c, v = 50, 50, 50
            scores.append((e + c + v) / 3)

        n = len(scores) if scores else 1
        total = sum(scores)
        final_score = min(round(total / n), 100)

        # 2. Strict Scale Logic (V90 - Orange High)
        if final_score >= 80:    cls, ico, txt = "risk-critical", "fa-biohazard", "maximo"   # 80-100: Max (Purple)
        elif final_score >= 60:  cls, ico, txt = "risk-critical", "fa-radiation", "critico"  # 60-79: Critical (Red)
        elif final_score >= 40:  cls, ico, txt = "risk-high",     "fa-fire",      "alto"     # 40-59: High (Orange)
        elif final_score >= 20:  cls, ico, txt = "risk-medium",   "fa-shield",    "medio"    # 20-39: Medium (Yellow)
        else:                    cls, ico, txt = "risk-low",      "fa-user-shield","bajo"    # 00-19: Low (Blue)
        
        color_class = f"text-{txt}"
        anim_class = f"color-cycle-{txt}"
        
        # 3. Dynamic Cards & Market Logic
        cards_html = ""
        timeline_events = []
        
        # Market Counters
        market_counts = {
            'banco': 0, 'card': 0, 'id': 0, 'pass': 0, 'email': 0
        }

        for i, f in enumerate(findings):
            # Resolve Name (Breach Title > Title > Value > ID)
            name = f.get('breach_title') or f.get('title') or f.get('value') or f.get('finding_id', 'Desconocido')
            name = name.replace('Breach: ', '')
            
            data_classes = f.get('breach_classes', [])
            b_date = f.get('breach_date') or f.get('captured_at', 'Fecha Desconocida')
            if 'T' in b_date: b_date = b_date.split('T')[0] # ISO to Date
            
            # --- Timeline Data ---
            if b_date and b_date != 'Fecha Desconocida':
                timeline_events.append({'date': b_date, 'name': name, 'desc': f.get('description', '')})
            
            # --- Market Value Accumulation ---
            if 'Saldos de cuenta' in data_classes or 'Números de cuenta bancaria' in data_classes: market_counts['banco'] += 1
            elif 'Información de tarjeta de crédito' in data_classes: market_counts['card'] += 1
            elif 'Identificaciones gubernamentales' in data_classes or 'Direcciones físicas' in data_classes: market_counts['id'] += 1
            elif 'Contraseñas' in data_classes: market_counts['pass'] += 1
            else: market_counts['email'] += 1 # Base entry fallthrough

            # Subtitle Logic
            if "Telegram" in name: type_lbl = "Venta de Identidades en la Dark Web"
            elif "Banorte" in name: type_lbl = "Institución Bancaria"
            elif "Círculo" in name or "Buró" in name: type_lbl = "Sociedad de Información Crediticia"
            elif "Ine" in name or "Gob" in name: type_lbl = "Entidad Gubernamental"
            else: type_lbl = "Filtración de Base de Datos"

            fscore = f.get('risk_score', 'P3')
            
            # --- RISK VISUALS ---
            if fscore == 'P0': 
                c_cls, c_ico = "risk-critical", "fa-biohazard"
                justif = "Crítico: Compromiso directo de credenciales o identidad."
            elif fscore == 'P1': 
                c_cls, c_ico = "risk-high", "fa-radiation"
                justif = "Alto: Impacto financiero probable o herramientas de ataque."
            elif fscore == 'P2': 
                c_cls, c_ico = "risk-medium", "fa-shield"
                justif = "Medio: Filtraciones confirmadas de credenciales."
            else: 
                c_cls, c_ico = "risk-low", "fa-shield-halved"
                justif = "Bajo: Exposición en servicios antiguos o de menor impacto."

            # --- DYNAMIC MITIGATION LOGIC (V85) ---
            steps = []
            
            pwd_tips = [
                f"<strong>Gestor de Contraseñas:</strong> Deja de reciclar claves. Usa 1Password o Bitwarden para proteger <em>{name}</em>.",
                f"<strong>Frase de Paso:</strong> En lugar de una palabra, usa una frase de 4 palabras aleatorias para tu cuenta de <em>{name}</em>.",
                f"<strong>Auditoría de Reutilización:</strong> Si usaste la clave de <em>{name}</em> en otro lado, cámbiala allá también."
            ]
            mfa_tips = [
                f"<strong>MFA Obligatorio:</strong> Activa autenticación de 2 pasos en <em>{name}</em> inmediatmente.",
                "<strong>Llave de Seguridad:</strong> Si es posible, usa una YubiKey o Passkey en lugar de SMS.",
                "<strong>Revisión de Accesos:</strong> Verifica en la configuración de seguridad qué dispositivos están conectados."
            ]
            
            if any(x in data_classes for x in ['Información de tarjeta de crédito', 'Números de cuenta bancaria', 'Saldos de cuenta']):
                steps.append("<strong>Bloqueo Financiero:</strong> Contacta a tu banco y solicita reposición de plásticos.")
                steps.append("<strong>Alerta de Fraude:</strong> Activa notificaciones SMS para cada retiro.")

            if 'Contraseñas' in data_classes:
                steps.append(pwd_tips[i % len(pwd_tips)])
                steps.append(mfa_tips[i % len(mfa_tips)])
            elif 'Pistas de contraseña' in data_classes:
                 steps.append("<strong>Cambia tus Preguntas:</strong> Las respuestas de seguridad 'madre/mascota' ya son públicas.")

            if 'Números de teléfono' in data_classes:
                steps.append("<strong>Anti-Smishing:</strong> Desconfía de SMS urgentes de supuestos bancos.")
            
            if 'Perfiles de redes sociales' in data_classes:
                steps.append("<strong>Privacidad:</strong> Revisa qué apps de terceros tienen acceso a tu perfil.")
            
            if 'Direcciones físicas' in data_classes:
                steps.append("<strong>Entorno Físico:</strong> Ten cuidado con correspondencia o visitas no solicitadas.")
            
            if not steps:
                steps.append("<strong>Rotación Preventiva:</strong> Cambia la clave por precaución.")
                steps.append("<strong>Sesiones Activas:</strong> Cierra sesión en todos los dispositivos.")

            steps = steps[:3]
            if len(steps) < 2:
                steps.append("<strong>Higiene Digital:</strong> Monitorea tu correo en busca de actividad inusual.")

            raw_desc = f.get('breach_desc') or f.get('snippet') or f.get('risk_rationale', '')
            if not raw_desc:
                raw_desc = f"Hallazgo detectado en {name}. Entidad: {f.get('entity', 'Desconocida')}."
            
            steps_html = "".join([f"<li>{s}</li>" for s in steps])

            cards_html += f"""
            <div class="card {c_cls}">
                <i class="card-icon fas {c_ico}"></i>
                <h3>{name}</h3>
                <span class="type-label">{type_lbl}</span>
                <div class="risk-box">{justif}</div>
                <span class="label-kpi">DETALLES DE LA AMENAZA</span>
                <p class="description-text">{raw_desc}</p>
                <span class="label-kpi">PASOS DE MITIGACIÓN:</span>
                <ul class="mitigation-list">{steps_html}</ul>
            </div>
            """
        
        # --- BUILD MARKET TABLE HTML ---
        market_rows = ""
        total_market_value = 0
        
        # Prices
        prices = {'banco': 150.00, 'card': 85.00, 'id': 20.00, 'pass': 5.00, 'email': 0.50}
        labels = {'banco': 'Acceso Bancario (Log/Saldo)', 'card': 'Tarjeta de Crédito (Fullz)', 'id': 'Identidad Completa (Scan)', 'pass': 'Credenciales (Email:Pass)', 'email': 'Datos de Contacto (Lead)'}
        
        for k, v in market_counts.items():
            if v > 0:
                subtotal = v * prices[k]
                total_market_value += subtotal
                market_rows += f"""
                <tr>
                    <td><span class="market-highlight">{labels[k]}</span></td>
                    <td style="text-align:center;">{v}</td>
                    <td style="text-align:right;">${prices[k]:.2f}</td>
                    <td style="text-align:right;" class="market-price">${subtotal:.2f}</td>
                </tr>
                """
        
        # Empty row if nothing found (unlikely in report)
        if not market_rows:
            market_rows = "<tr><td colspan='4'>No se detectaron activos monetizables directos.</td></tr>"

        # --- BUILD EXTRA SECTIONS HTML ---
        
        # 1. Timeline
        timeline_events.sort(key=lambda x: x['date'], reverse=True)
        timeline_html = ""
        for t in timeline_events:
            timeline_html += f"""
            <div class="timeline-item">
                <span class="timeline-date">{t['date']}</span>
                <div class="timeline-title">{t['name']}</div>
            </div>
            """
        
        # 2. Hacker Path (Narrative)
        # Simplified logic: If P0 exists -> High Impact Story
        if any(f.get('risk_score') == 'P0' for f in findings):
            hacker_story = """
            <div class="step"><div class="step-num">1</div><div class="step-text"><strong>Reconocimiento:</strong> El atacante compra el combo de <em>Telegram</em> y obtiene tu correo y contraseñas antiguas.</div></div>
            <div class="step"><div class="step-num">2</div><div class="step-text"><strong>Acceso Inicial:</strong> Prueba esas claves (Credential Stuffing) en servicios como <em>Dropbox</em> o <em>Adobe</em>.</div></div>
            <div class="step"><div class="step-num">3</div><div class="step-text"><strong>Escalada:</strong> Encuentra un patrón en tus contraseñas y logra acceder a tu correo principal.</div></div>
            <div class="step"><div class="step-num">4</div><div class="step-text" style="color:#ff2a2a;"><strong>Impacto Crítico:</strong> Con acceso al correo, restablece la contraseña de tu banca en línea (<em>Banorte</em>) y transfiere fondos.</div></div>
            """
        else:
            hacker_story = """
            <div class="step"><div class="step-num">1</div><div class="step-text"><strong>Recolección:</strong> El atacante descarga bases de datos públicas como <em>Trello</em>.</div></div>
            <div class="step"><div class="step-num">2</div><div class="step-text"><strong>Phishing:</strong> Usa tu número y nombre para enviarte SMS falsos (Smishing) haciéndose pasar por tu banco.</div></div>
            <div class="step"><div class="step-num">3</div><div class="step-text" style="color:#ffb800;"><strong>Intento de Estafa:</strong> Busca engañarte para que entregues tus claves vigentes.</div></div>
            """

        return f"""<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Inteligencia | MAPA-RD (V89)</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>{self._get_premium_style()}</style>
</head>
<body>
    <div class="container">
        <!-- HERO -->
        <div class="hero-top">
            <svg class="lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <div class="tag">REPORTE CONFIDENCIAL</div>
        </div>

        <h1>MAPA-RD: Exposición y Ruta de Cierre</h1>
        <div class="subtitle">Así se ve tu entorno digital desde fuera. Esto es lo que hay que cerrar primero.</div>
        <p class="description">
            Reporte generado para {client_name} ({report_id}).
        </p>

        <!-- SCORE SECTION -->
        <div class="section" style="border:none; padding-top:0;">
            <div class="master-score-card {cls}" style="--target-score: {final_score}%;">
                <div class="master-score-header">
                    <h3 style="letter-spacing: 0.4em; opacity: 0.7; margin-bottom: 2rem;">ÍNDICE DE RIESGO DIGITAL</h3>
                    
                    <div id="riskScore" class="main-score" data-target="{final_score}">0</div>
                    <div class="score-label" style="margin-top: 2rem; letter-spacing: 0.15em;">NIVEL {txt.upper()} - Acción Requerida</div>
                    
                    <div class="thermometer-container">
                        <div class="thermometer-fill">
                             <div class="thermometer-icon {anim_class} {color_class}">
                                <i class="fas {ico}"></i>
                             </div>
                        </div>
                    </div>
                </div>

                <div class="master-content-stack">
                    <div class="analysis-header" style="text-align:center;">
                        <h4 style="color: #fff; margin-bottom: 2.5rem; letter-spacing: 0.3em; font-weight: 700; font-size: 0.95rem; text-transform: uppercase; opacity: 0.9;">ANÁLISIS TÉCNICO (V79)</h4>
                        
                        <div class="glossary-container">
                            <span class="glossary-title">GLOSARIO DE CÁLCULO</span>
                            <p class="glossary-desc">El cálculo se basa en el promedio de las 3 variables (Exposición, Criticidad, Vigencia).</p>
                            
                            <div class="calc-box">
                                <div class="calc-row">
                                    <span>Suma de Riesgos:</span>
                                    <strong style="color: #ff2a2a;">{total:.1f} pts</strong>
                                </div>
                                <div class="calc-row">
                                    <span>Total Hallazgos:</span>
                                    <strong style="color: #00a3ff;">&divide; {n}</strong>
                                </div>
                                <div class="calc-row calc-last">
                                    <span>ÍNDICE FINAL:</span>
                                    <strong style="color: #00ff85;">= {final_score}</strong>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2 style="text-align:center;">VECTORES DE ATAQUE DETALLADOS</h2>
            <div class="grid cols-2">
                {cards_html}
            </div>
        </div>
        
        <!-- IMPACT SECTIONS (NEW V89 - DETAILED TABLE) -->
        <div class="section">
            <h2 style="color: #ff2a2a;">1. Valor de Tu Información en el Mercado Negro</h2>
            <p class="description" style="text-align:center;">Desglose estimado de tus activos digitales encontrados a la venta (Precios promedio Dark Web 2025).</p>
            
            <div class="impact-card" style="padding: 0; overflow: hidden; text-align: left;">
                <table class="market-table">
                    <thead>
                        <tr>
                            <th>Concepto / Activo</th>
                            <th style="text-align:center;">Cant.</th>
                            <th style="text-align:right;">Precio Unit.</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {market_rows}
                        <tr style="background: rgba(255, 42, 42, 0.1);">
                            <td><span style="color:#fff; font-weight:800; letter-spacing:0.05em;">VALOR TOTAL ESTIMADO</span></td>
                            <td></td>
                            <td></td>
                            <td style="text-align:right;"><span class="price-tag" style="font-size:1.5rem; margin:0;">${total_market_value:,.2f} USD</span></td>
                        </tr>
                    </tbody>
                </table>
                <div style="padding: 1.5rem; text-align:center; background: rgba(0,0,0,0.2);">
                    <p class="impact-note"><i class="fas fa-exclamation-triangle"></i> Este es el costo por el que un criminal adquiriría tu identidad digital hoy mismo.</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>2. Línea de Tiempo de Exposición</h2>
            <div class="timeline">
                {timeline_html}
            </div>
        </div>

        <div class="section">
            <h2 style="margin-bottom: 0.5rem;">3. Ruta de Ataque (Kill Chain)</h2>
            <p class="description" style="text-align:center; margin-bottom: 2rem;">Cómo un atacante conecta estos puntos para causar daño real.</p>
            <div class="hacker-path">
                {hacker_story}
            </div>
        </div>

        <div class="section" style="border: none; margin-top: 2rem;">
            <p style="font-size: 0.85rem; color: #6b7490; text-align: center;">
                Generado por Motor Cortex MAPA-RD • {date}
            </p>
        </div>
    </div>

    <!-- UPCOUNT ANIMATION SCRIPT -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {{
            const scoreElement = document.getElementById("riskScore");
            const targetScore = parseInt(scoreElement.getAttribute("data-target"));
            let currentScore = 0;
            const duration = 2500;
            const intervalTime = 20;
            const steps = duration / intervalTime;
            const increment = targetScore / steps;

            const updateColor = (score) => {{
                scoreElement.classList.remove("text-critico", "text-alto", "text-medio", "text-bajo", "text-nulo");
                // Strict 5-Level Logic (80/60/40/20)
                if (score >= 80) scoreElement.classList.add("text-critico"); // Max (Purple) reused for now or add text-maximo
                else if (score >= 60) scoreElement.classList.add("text-critico");
                else if (score >= 40) scoreElement.classList.add("text-alto"); // Orange
                else if (score >= 20) scoreElement.classList.add("text-medio");
                else scoreElement.classList.add("text-bajo");
            }};

            const counter = setInterval(() => {{
                currentScore += increment;
                if (currentScore >= targetScore) {{
                    currentScore = targetScore;
                    clearInterval(counter);
                }}
                const displayScore = Math.floor(currentScore);
                scoreElement.innerText = displayScore;
                updateColor(displayScore);
            }}, intervalTime);
        }});
    </script>
</body>
</html>"""
