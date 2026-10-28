import os
import sys
import pptx
from pptx.util import Inches, Pt
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN
import win32com.client

TEMPLATE_PATH = r'C:\Users\Vishal\Downloads\SIH2026-IDEA-Presentation-Format.pptx'
OUTPUT_PPTX_WORKSPACE = r'C:\sih\SIH2026_Streamline_FDR_Portal_Presentation.pptx'
OUTPUT_PPTX_DOWNLOADS = r'C:\Users\Vishal\Downloads\SIH2026_Streamline_FDR_Portal_Presentation.pptx'
OUTPUT_PDF_WORKSPACE = r'C:\sih\SIH2026_Streamline_FDR_Portal_Presentation.pdf'
OUTPUT_PDF_DOWNLOADS = r'C:\Users\Vishal\Downloads\SIH2026_Streamline_FDR_Portal_Presentation.pdf'

def build_presentation():
    prs = pptx.Presentation(TEMPLATE_PATH)
    
    # Colors
    ORANGE = RGBColor(217, 83, 30)      # SIH Orange
    DARK_BLUE = RGBColor(27, 42, 74)   # Header Navy
    BODY_DARK = RGBColor(40, 50, 65)   # Body Text
    GREEN = RGBColor(16, 124, 65)      # Verification Green
    GRAY = RGBColor(100, 116, 139)

    # ----------------------------------------------------
    # SLIDE 1: TITLE PAGE
    # ----------------------------------------------------
    slide1 = prs.slides[0]
    for shape in slide1.shapes:
        if shape.name == 'TextBox 9' and shape.has_text_frame:
            tf = shape.text_frame
            tf.clear()
            
            lines = [
                ("Problem Statement ID:", " 1632 / Custom AICTE Problem"),
                ("Problem Statement Title:", " Streamline of Security Deposit / FDR Portal"),
                ("Theme:", " Smart Automation / FinTech / Governance"),
                ("PS Category:", " Software (Web Application & Cryptographic Vault)"),
                ("Team ID:", " [Enter Your Team ID]"),
                ("Team Name:", " [Enter Your Team Name (Registered on Portal)]")
            ]
            
            for idx, (label, val) in enumerate(lines):
                p = tf.paragraphs[0] if idx == 0 else tf.add_paragraph()
                p.space_after = Pt(10)
                
                run1 = p.add_run()
                run1.text = label
                run1.font.bold = True
                run1.font.size = Pt(17)
                run1.font.color.rgb = ORANGE
                run1.font.name = "Segoe UI"
                
                run2 = p.add_run()
                run2.text = val
                run2.font.bold = False
                run2.font.size = Pt(17)
                run2.font.color.rgb = DARK_BLUE
                run2.font.name = "Segoe UI"

    # ----------------------------------------------------
    # SLIDE 2: PROPOSED SOLUTION
    # ----------------------------------------------------
    slide2 = prs.slides[1]
    for shape in slide2.shapes:
        if shape.name == 'Title 1' and shape.has_text_frame:
            p = shape.text_frame.paragraphs[0]
            p.text = "PROPOSED SOLUTION"
            p.font.bold = True
            p.font.size = Pt(28)
            p.font.color.rgb = DARK_BLUE
            
        elif shape.name == 'TextBox 8' and shape.has_text_frame:
            tf = shape.text_frame
            tf.clear()
            tf.word_wrap = True
            shape.top = Inches(1.5)
            shape.left = Inches(0.8)
            shape.width = Inches(11.8)
            shape.height = Inches(4.8)
            
            content_blocks = [
                ("1. Executive Summary & Core Concept:", [
                    "An enterprise-grade Zero-Trust FDR Treasury, Verification & Lifecycle Governance Portal that converts vulnerable paper receipts into cryptographically sealed, digitally verifiable financial assets.",
                    "Provides 360-degree governance over statutory deposits across 10,000+ technical institutions and 40+ scheduled commercial banks."
                ]),
                ("2. Addressing the Regulatory Pain Points:", [
                    "Zero Fraud & Bitwise Verification: Stops forged FDR certificates using instant document SHA-256 matching and AES-256-GCM envelope encryption.",
                    "Automated Lien Enforcement: Eliminates unauthorized encashment; verifies AICTE joint lien clause directly with bank systems.",
                    "End-to-End Maturity Tracking: Segregates principal, interest, maturity proceeds, and liquid cash with automated 90/60/30-day alerts."
                ]),
                ("3. Key Innovations & Uniqueness:", [
                    "TrueType CMap Font-Decoding OCR: Proprietary engine extracts data from any Indian bank PDF (including font-subset encoded receipts).",
                    "RFC 8785 Merkle-Chained Audit Trail: Every approval, decision, and status transition is hashed into an immutable cryptographic chain."
                ])
            ]
            
            first_p = True
            for header, items in content_blocks:
                p_head = tf.paragraphs[0] if first_p else tf.add_paragraph()
                first_p = False
                p_head.space_before = Pt(8)
                p_head.space_after = Pt(3)
                r_h = p_head.add_run()
                r_h.text = header
                r_h.font.bold = True
                r_h.font.size = Pt(14)
                r_h.font.color.rgb = ORANGE
                r_h.font.name = "Segoe UI"
                
                for item in items:
                    p_bullet = tf.add_paragraph()
                    p_bullet.level = 1
                    p_bullet.space_after = Pt(2)
                    r_b = p_bullet.add_run()
                    r_b.text = "• " + item
                    r_b.font.size = Pt(12)
                    r_b.font.color.rgb = BODY_DARK
                    r_b.font.name = "Segoe UI"

    # ----------------------------------------------------
    # SLIDE 3: TECHNICAL APPROACH
    # ----------------------------------------------------
    slide3 = prs.slides[2]
    for shape in slide3.shapes:
        if shape.name == 'Title 1' and shape.has_text_frame:
            p = shape.text_frame.paragraphs[0]
            p.text = "TECHNICAL APPROACH & ARCHITECTURE"
            p.font.bold = True
            p.font.size = Pt(28)
            p.font.color.rgb = DARK_BLUE
            
        elif shape.name == 'TextBox 8' and shape.has_text_frame:
            tf = shape.text_frame
            tf.clear()
            tf.word_wrap = True
            shape.top = Inches(1.5)
            shape.left = Inches(0.8)
            shape.width = Inches(11.8)
            shape.height = Inches(4.8)
            
            content_blocks = [
                ("1. Modern Full-Stack Technology Stack:", [
                    "Frontend: React 18, TypeScript, Tailwind CSS, Lucide Icons, Vite (HMR under 700ms).",
                    "Backend: Laravel 11 / PHP 8.4 RESTful Microservices, Sanctum Token Architecture, MySQL 8.4 with Strict Acid transactions.",
                    "Cryptography: Hardware KMS Envelope Encryption (AES-256-GCM), Peppered Argon2id (64MB memory hardness), SHA-256 Bitwise Sealing.",
                    "AI/OCR Layer: Native /ToUnicode CMap TrueType font parser, multi-bank regex pattern recognizer, confidence scoring."
                ]),
                ("2. End-to-End Workflow & Pipeline:", [
                    "Phase 1 (Ingest): Institution uploads physical/e-FDR receipt -> Pre-submission OCR instantly auto-populates fields with 96%+ accuracy.",
                    "Phase 2 (Vault & Validate): Document encrypted via Envelope Encryption -> Discrepancy Engine evaluates 10+ regulatory rules.",
                    "Phase 3 (Bank Verification): Multi-bank adapter initiates CBS API / SFTP verification with bank branch & verifies AICTE joint lien.",
                    "Phase 4 (Treasury & Audit): Real-time liquidity dashboard, automated maturity escalation, and Merkle-chained immutable logging."
                ]),
                ("3. Working Prototype Status:", [
                    "100% Functional Prototype: 25 automated feature tests passing with 145 assertions, live OCR auto-fill, and tamper verification."
                ])
            ]
            
            first_p = True
            for header, items in content_blocks:
                p_head = tf.paragraphs[0] if first_p else tf.add_paragraph()
                first_p = False
                p_head.space_before = Pt(8)
                p_head.space_after = Pt(3)
                r_h = p_head.add_run()
                r_h.text = header
                r_h.font.bold = True
                r_h.font.size = Pt(14)
                r_h.font.color.rgb = ORANGE
                r_h.font.name = "Segoe UI"
                
                for item in items:
                    p_bullet = tf.add_paragraph()
                    p_bullet.level = 1
                    p_bullet.space_after = Pt(2)
                    r_b = p_bullet.add_run()
                    r_b.text = "• " + item
                    r_b.font.size = Pt(12)
                    r_b.font.color.rgb = BODY_DARK
                    r_b.font.name = "Segoe UI"

    # ----------------------------------------------------
    # SLIDE 4: FEASIBILITY AND VIABILITY
    # ----------------------------------------------------
    slide4 = prs.slides[3]
    for shape in slide4.shapes:
        if shape.name == 'Title 1' and shape.has_text_frame:
            p = shape.text_frame.paragraphs[0]
            p.text = "FEASIBILITY AND VIABILITY"
            p.font.bold = True
            p.font.size = Pt(28)
            p.font.color.rgb = DARK_BLUE
            
        elif shape.name == 'TextBox 8' and shape.has_text_frame:
            tf = shape.text_frame
            tf.clear()
            tf.word_wrap = True
            shape.top = Inches(1.5)
            shape.left = Inches(0.8)
            shape.width = Inches(11.8)
            shape.height = Inches(4.8)
            
            content_blocks = [
                ("1. Operational & Technical Feasibility:", [
                    "Zero Vendor Lock-in: Built entirely on open-source, highly auditable stacks (React, Laravel, MySQL, OpenSSL).",
                    "Seamless Bank Adoption: Flexible verification architecture supports Modern REST/SFTP Bank APIs and fallback two-way digital NOC portals.",
                    "Cloud & On-Premises Ready: Low-overhead, containerized architecture runs effortlessly on NIC GovCloud or AWS/MeitY-empanelled clouds."
                ]),
                ("2. Identified Potential Challenges & Risks:", [
                    "Varied FDR Receipt Formats: 40+ banks have distinct receipt layouts, fonts, and terminologies.",
                    "Lien Release Discrepancies: Branch-level officers encashing or closing deposits without central AICTE intimation.",
                    "Peak Traffic Surges: Thousands of technical colleges uploading documents during annual AICTE approval windows."
                ]),
                ("3. Robust Mitigation Strategies:", [
                    "CMap-Aware OCR + Human-in-the-Loop: Deep font decoding handles unstructured PDFs; highlighted fields allow quick manual confirmation.",
                    "Bilateral Automated Reconciliation: Periodic scheduled bank balance sync compares recorded deposits against CBS statements.",
                    "Asynchronous Job Queues: Background workers process heavy OCR and AES-256 encryption tasks without degrading UI responsiveness."
                ])
            ]
            
            first_p = True
            for header, items in content_blocks:
                p_head = tf.paragraphs[0] if first_p else tf.add_paragraph()
                first_p = False
                p_head.space_before = Pt(8)
                p_head.space_after = Pt(3)
                r_h = p_head.add_run()
                r_h.text = header
                r_h.font.bold = True
                r_h.font.size = Pt(14)
                r_h.font.color.rgb = ORANGE
                r_h.font.name = "Segoe UI"
                
                for item in items:
                    p_bullet = tf.add_paragraph()
                    p_bullet.level = 1
                    p_bullet.space_after = Pt(2)
                    r_b = p_bullet.add_run()
                    r_b.text = "• " + item
                    r_b.font.size = Pt(12)
                    r_b.font.color.rgb = BODY_DARK
                    r_b.font.name = "Segoe UI"

    # ----------------------------------------------------
    # SLIDE 5: IMPACT AND BENEFITS
    # ----------------------------------------------------
    slide5 = prs.slides[4]
    for shape in slide5.shapes:
        if shape.name == 'Title 1' and shape.has_text_frame:
            p = shape.text_frame.paragraphs[0]
            p.text = "IMPACT AND TANGIBLE BENEFITS"
            p.font.bold = True
            p.font.size = Pt(28)
            p.font.color.rgb = DARK_BLUE
            
        elif shape.name == 'TextBox 8' and shape.has_text_frame:
            tf = shape.text_frame
            tf.clear()
            tf.word_wrap = True
            shape.top = Inches(1.5)
            shape.left = Inches(0.8)
            shape.width = Inches(11.8)
            shape.height = Inches(4.8)
            
            content_blocks = [
                ("1. Impact on Target Stakeholders:", [
                    "For AICTE Regulators: Single-pane-of-glass dashboard monitoring ₹10,000+ Cr across 10,000+ technical institutions nationwide.",
                    "For Institutions: Self-service portal with instant receipt pre-scan, real-time verification tracking, and automated renewal guidance.",
                    "For Partner Banks: Standardized API/SFTP verification endpoints eliminating tedious physical branch verification letters."
                ]),
                ("2. Measurable Economic & Governance Benefits:", [
                    "100% Elimination of Counterfeit FDRs: Cryptographic SHA-256 bitwise checks guarantee forged receipts cannot enter the portal.",
                    "95% Faster Verification: Cuts administrative processing turnaround from 4-6 weeks of postal verification down to under 30 seconds.",
                    "Zero Lost Proceeds: Eliminates idle funds and forgotten maturities through multi-stage smart renewal and maturity reminders.",
                    "Complete Audit Accountability: Merkle-chained tamper-evident event log ensures decisions are permanently traceable for CAG audits."
                ]),
                ("3. Environmental & Digital India Alignment:", [
                    "Paperless Governance: Completely eliminates couriers, physical paper records, and manual stamp verifications."
                ])
            ]
            
            first_p = True
            for header, items in content_blocks:
                p_head = tf.paragraphs[0] if first_p else tf.add_paragraph()
                first_p = False
                p_head.space_before = Pt(8)
                p_head.space_after = Pt(3)
                r_h = p_head.add_run()
                r_h.text = header
                r_h.font.bold = True
                r_h.font.size = Pt(14)
                r_h.font.color.rgb = ORANGE
                r_h.font.name = "Segoe UI"
                
                for item in items:
                    p_bullet = tf.add_paragraph()
                    p_bullet.level = 1
                    p_bullet.space_after = Pt(2)
                    r_b = p_bullet.add_run()
                    r_b.text = "• " + item
                    r_b.font.size = Pt(12)
                    r_b.font.color.rgb = BODY_DARK
                    r_b.font.name = "Segoe UI"

    # ----------------------------------------------------
    # SLIDE 6: RESEARCH AND REFERENCES
    # ----------------------------------------------------
    slide6 = prs.slides[5]
    for shape in slide6.shapes:
        if shape.name == 'Title 1' and shape.has_text_frame:
            p = shape.text_frame.paragraphs[0]
            p.text = "RESEARCH AND REFERENCES"
            p.font.bold = True
            p.font.size = Pt(28)
            p.font.color.rgb = DARK_BLUE
            
        elif shape.name == 'TextBox 8' and shape.has_text_frame:
            tf = shape.text_frame
            tf.clear()
            tf.word_wrap = True
            shape.top = Inches(1.5)
            shape.left = Inches(0.8)
            shape.width = Inches(11.8)
            shape.height = Inches(4.8)
            
            content_blocks = [
                ("1. Regulatory Frameworks & Official Norms:", [
                    "AICTE Approval Process Handbook (APH 2024-2027) - Statutory Security Deposit norms and Joint FDR Lien rules.",
                    "Reserve Bank of India (RBI) Master Directions - Term Deposits, Lien Marking Protocols & Safe Custody Standards.",
                    "Information Technology Act, 2000 & Digital Personal Data Protection (DPDP) Act, 2023 - Digital signatures & data sovereignty."
                ]),
                ("2. Cryptographic & Security Standards:", [
                    "NIST Special Publication 800-38D - Recommendation for Block Cipher Modes of Operation: Galois/Counter Mode (AES-256-GCM).",
                    "RFC 8785 - JSON Canonicalization Scheme (JCS) for Merkle hash chaining and immutable ledger auditing.",
                    "RFC 9106 - Argon2id Password-Based Key Derivation Function (Memory-hard KMS pepper hashing)."
                ]),
                ("3. Document Engineering & Modern UI Benchmarks:", [
                    "Adobe PDF 1.4 / 1.7 Reference Manual (ISO 32000-1) - TrueType / CMap ToUnicode Font Mapping & Stream Decoding.",
                    "OWASP Top 10 API Security Risks (2023) - Broken Object Level Authorization (BOLA) & Envelope Encryption Best Practices."
                ])
            ]
            
            first_p = True
            for header, items in content_blocks:
                p_head = tf.paragraphs[0] if first_p else tf.add_paragraph()
                first_p = False
                p_head.space_before = Pt(8)
                p_head.space_after = Pt(3)
                r_h = p_head.add_run()
                r_h.text = header
                r_h.font.bold = True
                r_h.font.size = Pt(14)
                r_h.font.color.rgb = ORANGE
                r_h.font.name = "Segoe UI"
                
                for item in items:
                    p_bullet = tf.add_paragraph()
                    p_bullet.level = 1
                    p_bullet.space_after = Pt(2)
                    r_b = p_bullet.add_run()
                    r_b.text = "• " + item
                    r_b.font.size = Pt(12)
                    r_b.font.color.rgb = BODY_DARK
                    r_b.font.name = "Segoe UI"

    # ----------------------------------------------------
    # DELETE SLIDE 7 (Instruction Slide)
    # ----------------------------------------------------
    if len(prs.slides) >= 7:
        rId = prs.slides._sldIdLst[6].rId
        prs.part.drop_rel(rId)
        del prs.slides._sldIdLst[6]
        print("Slide 7 (instructions) deleted successfully. Deck now has exactly 6 slides.")

    # Save PPTX
    prs.save(OUTPUT_PPTX_WORKSPACE)
    prs.save(OUTPUT_PPTX_DOWNLOADS)
    print(f"PPTX saved to:\n  {OUTPUT_PPTX_WORKSPACE}\n  {OUTPUT_PPTX_DOWNLOADS}")

    # Export to PDF via PowerPoint COM
    try:
        ppt_app = win32com.client.Dispatch("PowerPoint.Application")
        deck = ppt_app.Presentations.Open(os.path.abspath(OUTPUT_PPTX_WORKSPACE), WithWindow=False)
        deck.SaveAs(os.path.abspath(OUTPUT_PDF_WORKSPACE), 32) # 32 = ppSaveAsPDF
        deck.SaveAs(os.path.abspath(OUTPUT_PDF_DOWNLOADS), 32)
        deck.Close()
        ppt_app.Quit()
        print(f"PDF exported successfully to:\n  {OUTPUT_PDF_WORKSPACE}\n  {OUTPUT_PDF_DOWNLOADS}")
    except Exception as e:
        print(f"PowerPoint COM export error: {e}")

if __name__ == '__main__':
    build_presentation()
