export type Status =
  | "open"
  | "wachtend"
  | "behandeling"
  | "opgelost"
  | "gesloten";

export const statusLabels: Record<Status, string> = {
  open: "Open",
  wachtend: "Wacht op reactie",
  behandeling: "In behandeling",
  opgelost: "Opgelost",
  gesloten: "Gesloten",
};

export type Prioriteit = "laag" | "normaal" | "hoog" | "kritiek";

export interface Ticket {
  id: string;
  nummer: string;
  onderwerp: string;
  melder: string;
  afdeling: string;
  behandelaar: string;
  status: Status;
  prioriteit: Prioriteit;
  categorie: string;
  aangemaakt: string;
  laatstBijgewerkt: string;
  kanaal: "E-mail" | "Telefoon" | "Portaal" | "Teams";
}

export const tickets: Ticket[] = [
  { id: "1", nummer: "T-2841", onderwerp: "Outlook synchroniseert niet meer met Exchange", melder: "Sanne de Vries", afdeling: "Verkoop", behandelaar: "Jeroen Bakker", status: "behandeling", prioriteit: "hoog", categorie: "E-mail", aangemaakt: "Vandaag 09:14", laatstBijgewerkt: "12 min geleden", kanaal: "E-mail" },
  { id: "2", nummer: "T-2840", onderwerp: "Nieuwe medewerker onboarden — laptop + accounts", melder: "Karin Peters", afdeling: "HR", behandelaar: "Mila van den Berg", status: "open", prioriteit: "normaal", categorie: "Onboarding", aangemaakt: "Vandaag 08:47", laatstBijgewerkt: "38 min geleden", kanaal: "Portaal" },
  { id: "3", nummer: "T-2839", onderwerp: "Printer Marketing-4 print blanco pagina's", melder: "Tom Willems", afdeling: "Marketing", behandelaar: "Jeroen Bakker", status: "wachtend", prioriteit: "laag", categorie: "Printer", aangemaakt: "Vandaag 08:12", laatstBijgewerkt: "1 uur geleden", kanaal: "Telefoon" },
  { id: "4", nummer: "T-2838", onderwerp: "VPN-verbinding valt weg na 5 minuten", melder: "Priya Ramautar", afdeling: "Consultancy", behandelaar: "Faisal El Amrani", status: "behandeling", prioriteit: "hoog", categorie: "Netwerk", aangemaakt: "Gisteren 16:22", laatstBijgewerkt: "2 uur geleden", kanaal: "Teams" },
  { id: "5", nummer: "T-2837", onderwerp: "CRM: klantkaart Van Elst B.V. toont verkeerde omzet", melder: "Bram Kooistra", afdeling: "Verkoop", behandelaar: "Mila van den Berg", status: "open", prioriteit: "kritiek", categorie: "CRM", aangemaakt: "Gisteren 15:04", laatstBijgewerkt: "3 uur geleden", kanaal: "E-mail" },
  { id: "6", nummer: "T-2836", onderwerp: "Wachtwoord reset — geen SMS ontvangen", melder: "Fleur Janssen", afdeling: "Financiën", behandelaar: "Mila van den Berg", status: "opgelost", prioriteit: "normaal", categorie: "Account", aangemaakt: "Gisteren 11:31", laatstBijgewerkt: "Gisteren 14:02", kanaal: "Portaal" },
  { id: "7", nummer: "T-2835", onderwerp: "Verzoek nieuwe monitor 27\" — thuiswerkplek", melder: "Ravi Sharma", afdeling: "Development", behandelaar: "Faisal El Amrani", status: "wachtend", prioriteit: "laag", categorie: "Hardware", aangemaakt: "Gisteren 10:08", laatstBijgewerkt: "Gisteren 13:45", kanaal: "Portaal" },
  { id: "8", nummer: "T-2834", onderwerp: "Teams-meeting crasht bij scherm delen", melder: "Ilse Groen", afdeling: "Directie", behandelaar: "Jeroen Bakker", status: "behandeling", prioriteit: "hoog", categorie: "Software", aangemaakt: "Gisteren 09:20", laatstBijgewerkt: "Gisteren 12:11", kanaal: "Telefoon" },
  { id: "9", nummer: "T-2833", onderwerp: "Backup-job PRD-SQL02 gefaald 3x", melder: "Monitoring", afdeling: "Systeem", behandelaar: "Faisal El Amrani", status: "opgelost", prioriteit: "kritiek", categorie: "Infra", aangemaakt: "17 jul", laatstBijgewerkt: "18 jul", kanaal: "E-mail" },
  { id: "10", nummer: "T-2832", onderwerp: "Nieuwe telefoonnummers toevoegen aan telefoonlijst", melder: "Karin Peters", afdeling: "HR", behandelaar: "Mila van den Berg", status: "gesloten", prioriteit: "laag", categorie: "Tools", aangemaakt: "16 jul", laatstBijgewerkt: "17 jul", kanaal: "Portaal" },
  { id: "11", nummer: "T-2831", onderwerp: "Toegangsrechten SharePoint 'Offertes 2026'", melder: "Bram Kooistra", afdeling: "Verkoop", behandelaar: "Mila van den Berg", status: "gesloten", prioriteit: "normaal", categorie: "Rechten", aangemaakt: "15 jul", laatstBijgewerkt: "16 jul", kanaal: "E-mail" },
];

export interface LogItem {
  tijd: string;
  actor: string;
  actie: string;
  detail?: string;
  type: "status" | "reactie" | "intern" | "systeem" | "email";
}

export const ticketLog: LogItem[] = [
  { tijd: "Vandaag 09:14", actor: "Sanne de Vries", actie: "Ticket aangemaakt via e-mail", type: "email", detail: "Onderwerp: 'Outlook doet niks meer' — automatisch geclassificeerd als E-mail/Exchange." },
  { tijd: "Vandaag 09:16", actor: "MailMind", actie: "AI-classificatie voltooid", type: "systeem", detail: "Categorie: E-mail · Voorgestelde KB-artikelen: 3 · Vertrouwen 92%" },
  { tijd: "Vandaag 09:22", actor: "Mila van den Berg", actie: "Toegewezen aan Jeroen Bakker", type: "systeem" },
  { tijd: "Vandaag 09:41", actor: "Jeroen Bakker", actie: "Reactie verstuurd naar melder", type: "reactie", detail: "Hoi Sanne, kun je Outlook één keer volledig afsluiten (ook via Taakbeheer) en opnieuw openen? Zo niet, dan graag even bellen." },
  { tijd: "Vandaag 10:03", actor: "Sanne de Vries", actie: "Antwoord ontvangen", type: "email", detail: "Herstart heeft niks veranderd, foutmelding 0x8004010F blijft komen." },
  { tijd: "Vandaag 10:12", actor: "Jeroen Bakker", actie: "Interne notitie", type: "intern", detail: "0x8004010F = corrupt OST-bestand. Herbouw OST-profiel plannen, laptop staat morgen op kantoor." },
  { tijd: "Vandaag 10:15", actor: "Jeroen Bakker", actie: "Status gewijzigd naar In behandeling", type: "status" },
];

export const kbSuggesties = [
  { id: "KB-142", titel: "Outlook OST-profiel opnieuw opbouwen", views: 1284, actueel: true },
  { id: "KB-098", titel: "Foutcode 0x8004010F — Exchange verbinding", views: 872, actueel: true },
  { id: "KB-211", titel: "Wachtwoord Office 365 opnieuw instellen", views: 3401, actueel: false },
];

export interface MailMindItem {
  id: string;
  onderwerp: string;
  van: string;
  ontvangen: string;
  fase: "ontvangen" | "geanalyseerd" | "concept" | "gepubliceerd";
  categorie?: string;
  vertrouwen?: number;
  kbConcept?: string;
}

export const mailmindQueue: MailMindItem[] = [
  { id: "M-8821", onderwerp: "Onedrive synchroniseert map 'Projecten' niet meer", van: "l.smit@vanpunt.nl", ontvangen: "2 min geleden", fase: "ontvangen" },
  { id: "M-8820", onderwerp: "Kan ik een extra dockingstation krijgen thuis?", van: "d.vermeer@vanpunt.nl", ontvangen: "8 min geleden", fase: "ontvangen" },
  { id: "M-8819", onderwerp: "Wachtwoord vergeten voor CRM-omgeving", van: "n.kooi@klant-relatie.nl", ontvangen: "14 min geleden", fase: "geanalyseerd", categorie: "Account", vertrouwen: 96 },
  { id: "M-8818", onderwerp: "Printer op 2e verdieping staat offline", van: "r.mulder@vanpunt.nl", ontvangen: "22 min geleden", fase: "geanalyseerd", categorie: "Printer", vertrouwen: 88 },
  { id: "M-8817", onderwerp: "Hoe stel ik automatisch antwoord in tijdens vakantie?", van: "e.hendriks@vanpunt.nl", ontvangen: "41 min geleden", fase: "concept", categorie: "E-mail", vertrouwen: 94, kbConcept: "Automatisch antwoord instellen in Outlook (web + desktop)" },
  { id: "M-8816", onderwerp: "MFA-app werkt niet na nieuwe telefoon", van: "j.oosterhof@vanpunt.nl", ontvangen: "1 uur geleden", fase: "concept", categorie: "Account", vertrouwen: 91, kbConcept: "MFA opnieuw koppelen aan nieuw toestel" },
  { id: "M-8815", onderwerp: "Foutmelding bij openen offertes in CRM", van: "b.kooistra@vanpunt.nl", ontvangen: "3 uur geleden", fase: "gepubliceerd", categorie: "CRM", vertrouwen: 97, kbConcept: "CRM — offertemodule: foutmelding 'template niet gevonden'" },
  { id: "M-8814", onderwerp: "Nieuwe medewerker per 1 aug — accounts aanvragen", van: "hr@vanpunt.nl", ontvangen: "5 uur geleden", fase: "gepubliceerd", categorie: "Onboarding", vertrouwen: 99, kbConcept: "Onboarding-checklist nieuwe medewerker" },
];

// ─── Kennisbank ────────────────────────────────────────────────────────────
export interface KbArtikel {
  id: string;
  titel: string;
  categorie: string;
  auteur: string;
  bijgewerkt: string;
  views: number;
  concept: boolean; // AI-gegenereerd concept vs gepubliceerd
  bron?: string;
  gekoppeldeTickets: string[];
  inhoud: string;
}
export const kbArtikelen: KbArtikel[] = [
  { id: "KB-142", titel: "Outlook OST-profiel opnieuw opbouwen", categorie: "E-mail", auteur: "Jeroen Bakker", bijgewerkt: "3 dagen geleden", views: 1284, concept: false, gekoppeldeTickets: ["T-2841", "T-2701"], inhoud: "Wanneer Outlook foutmelding 0x8004010F geeft, is het OST-bestand meestal corrupt. Sluit Outlook volledig af, ga naar %localappdata%\\Microsoft\\Outlook, hernoem het .ost bestand naar .ost.bak en start Outlook opnieuw. Het profiel wordt automatisch opnieuw opgebouwd (kan 10–30 min duren bij grote mailboxen)." },
  { id: "KB-098", titel: "Foutcode 0x8004010F — Exchange verbinding", categorie: "E-mail", auteur: "Faisal El Amrani", bijgewerkt: "1 week geleden", views: 872, concept: false, gekoppeldeTickets: ["T-2841"], inhoud: "0x8004010F duidt op een probleem met het standaard verzendaccount of een corrupt profiel. Controleer eerst het standaardaccount in Bestand → Accountinstellingen." },
  { id: "KB-211", titel: "Wachtwoord Office 365 opnieuw instellen", categorie: "Account", auteur: "Mila van den Berg", bijgewerkt: "2 maanden geleden", views: 3401, concept: false, gekoppeldeTickets: [], inhoud: "Ga naar passwordreset.microsoftonline.com en volg de stappen. MFA-code komt binnen op de bij HR bekende telefoon." },
  { id: "KB-C-018", titel: "Automatisch antwoord instellen in Outlook (web + desktop)", categorie: "E-mail", auteur: "MailMind AI", bijgewerkt: "41 min geleden", views: 0, concept: true, bron: "M-8817", gekoppeldeTickets: [], inhoud: "Desktop: Bestand → Automatische antwoorden → periode instellen. Web: tandwiel → Alle instellingen bekijken → E-mail → Automatische antwoorden. Formuleer aparte teksten voor binnen en buiten organisatie." },
  { id: "KB-C-019", titel: "MFA opnieuw koppelen aan nieuw toestel", categorie: "Account", auteur: "MailMind AI", bijgewerkt: "1 uur geleden", views: 0, concept: true, bron: "M-8816", gekoppeldeTickets: [], inhoud: "Meld je aan op mysignins.microsoft.com, verwijder het oude toestel en scan de QR-code met de Microsoft Authenticator op je nieuwe telefoon." },
  { id: "KB-155", titel: "VPN Fortinet — 'Credential rejected' bij inloggen", categorie: "Netwerk", auteur: "Faisal El Amrani", bijgewerkt: "2 weken geleden", views: 542, concept: false, gekoppeldeTickets: ["T-2838"], inhoud: "Wachtwoord verlopen? Wachtwoord bevat een speciaal teken dat FortiClient niet goed doorgeeft? Reset via portal en probeer opnieuw." },
  { id: "KB-176", titel: "Nieuwe medewerker — accounts & hardware checklist", categorie: "Onboarding", auteur: "Mila van den Berg", bijgewerkt: "5 dagen geleden", views: 1122, concept: false, gekoppeldeTickets: ["T-2840"], inhoud: "1. AD-account 2. Licenties (E3 + Copilot indien nodig) 3. Laptop uit voorraad 4. Docking + toetsenbord 5. Toegang tot afdelingsmap 6. Introductie MFA." },
  { id: "KB-201", titel: "Printer op 2e verdieping — herstartprocedure", categorie: "Printer", auteur: "Jeroen Bakker", bijgewerkt: "1 maand geleden", views: 233, concept: false, gekoppeldeTickets: ["T-2839"], inhoud: "Zet de printer volledig uit met de schakelaar aan de achterkant, wacht 30 seconden, zet aan en wacht 2 minuten op initialisatie voordat je opnieuw print." },
];

// ─── Verbeterpunten ────────────────────────────────────────────────────────
export type VerbeterStatus = "voorgesteld" | "in-uitvoering" | "afgerond";
export const verbeterStatusLabels: Record<VerbeterStatus, string> = {
  voorgesteld: "Voorgesteld",
  "in-uitvoering": "In uitvoering",
  afgerond: "Afgerond",
};
export interface Verbeterpunt {
  id: string;
  titel: string;
  indiener: string;
  eigenaar: string;
  status: VerbeterStatus;
  impact: "klein" | "middel" | "groot";
  aangemaakt: string;
  beschrijving: string;
}
export const verbeterpunten: Verbeterpunt[] = [
  { id: "VP-034", titel: "OST-herbouw automatiseren met PowerShell-script", indiener: "Jeroen Bakker", eigenaar: "Faisal El Amrani", status: "in-uitvoering", impact: "middel", aangemaakt: "12 jul", beschrijving: "Terugkerende Outlook-tickets vragen elke keer 20 minuten handwerk. Script uitrollen via Intune." },
  { id: "VP-033", titel: "MailMind-drempel verlagen naar 85% vertrouwen", indiener: "Mila van den Berg", eigenaar: "Mila van den Berg", status: "voorgesteld", impact: "klein", aangemaakt: "14 jul", beschrijving: "Nu blijven te veel mails hangen in 'geanalyseerd'. Zie of we bij 85% al direct een concept kunnen genereren." },
  { id: "VP-032", titel: "Toetsenborden per default draadloos bestellen", indiener: "Karin Peters", eigenaar: "Mila van den Berg", status: "afgerond", impact: "klein", aangemaakt: "1 jul", beschrijving: "Bespaart kabelchaos op flexplekken. Contract met leverancier is aangepast." },
  { id: "VP-031", titel: "Kennisbank-artikelen automatisch verlopen na 6 mnd", indiener: "Faisal El Amrani", eigenaar: "Jeroen Bakker", status: "in-uitvoering", impact: "middel", aangemaakt: "28 jun", beschrijving: "Verouderde artikelen krijgen automatisch een 'verouderd'-badge en gaan naar review-lijst." },
  { id: "VP-030", titel: "CyberRisico-review verplicht bij nieuwe leverancier", indiener: "Ilse Groen", eigenaar: "Faisal El Amrani", status: "voorgesteld", impact: "groot", aangemaakt: "22 jun", beschrijving: "Onboarding-formulier vult automatisch een risico-item aan in CyberRisico-module." },
];

// ─── Reflectie ─────────────────────────────────────────────────────────────
export interface Reflectie {
  id: string;
  datum: string;
  auteur: string;
  onderwerp: string;
  notitie: string;
}
export const reflecties: Reflectie[] = [
  { id: "R-088", datum: "22 jul", auteur: "Mila van den Berg", onderwerp: "Piek maandagochtend", notitie: "3 mensen tegelijk op MFA-issues; misschien maandag om 08:00 alvast een KB-mail broadcasten." },
  { id: "R-087", datum: "18 jul", auteur: "Jeroen Bakker", onderwerp: "OST-tickets", notitie: "Vierde OST-ticket deze week. Verbeterpunt VP-034 aangemaakt." },
  { id: "R-086", datum: "15 jul", auteur: "Faisal El Amrani", onderwerp: "Backup-monitoring", notitie: "PRD-SQL02 gaf 3x achter elkaar fout. Alerting-drempel bijgesteld." },
  { id: "R-085", datum: "10 jul", auteur: "Mila van den Berg", onderwerp: "Onboarding-flow", notitie: "Nieuwe collega had pas na 2 dagen SharePoint-toegang. Checklist aanscherpen." },
];

// ─── Voorraad ──────────────────────────────────────────────────────────────
export interface VoorraadItem {
  id: string;
  barcode: string;
  naam: string;
  categorie: string;
  aantal: number;
  minimum: number;
  locatie: string;
  laatstMutatie: string;
}
export const voorraadItems: VoorraadItem[] = [
  { id: "V-001", barcode: "8710438920014", naam: "Dell Latitude 5450 · i7 · 16GB", categorie: "Laptop", aantal: 3, minimum: 4, locatie: "Magazijn A · rek 2", laatstMutatie: "vandaag" },
  { id: "V-002", barcode: "8710438920021", naam: "Lenovo ThinkPad T14 · i5 · 16GB", categorie: "Laptop", aantal: 7, minimum: 3, locatie: "Magazijn A · rek 2", laatstMutatie: "gisteren" },
  { id: "V-003", barcode: "8710438920038", naam: "Dell dockingstation WD19S", categorie: "Accessoire", aantal: 12, minimum: 5, locatie: "Magazijn A · rek 3", laatstMutatie: "3 dagen" },
  { id: "V-004", barcode: "8710438920045", naam: "Logitech MX Master 3S", categorie: "Muis", aantal: 2, minimum: 6, locatie: "Magazijn B · lade 1", laatstMutatie: "vandaag" },
  { id: "V-005", barcode: "8710438920052", naam: "Logitech MX Keys draadloos toetsenbord", categorie: "Toetsenbord", aantal: 9, minimum: 4, locatie: "Magazijn B · lade 1", laatstMutatie: "6 dagen" },
  { id: "V-006", barcode: "8710438920069", naam: "Dell UltraSharp 27\" U2723QE", categorie: "Monitor", aantal: 4, minimum: 2, locatie: "Magazijn A · rek 4", laatstMutatie: "1 week" },
  { id: "V-007", barcode: "8710438920076", naam: "Jabra Evolve2 65 headset", categorie: "Headset", aantal: 1, minimum: 5, locatie: "Magazijn B · lade 2", laatstMutatie: "vandaag" },
  { id: "V-008", barcode: "8710438920083", naam: "USB-C kabel 2m", categorie: "Kabel", aantal: 34, minimum: 10, locatie: "Magazijn B · lade 3", laatstMutatie: "2 weken" },
  { id: "V-009", barcode: "8710438920090", naam: "iPhone 15 · 128GB", categorie: "Telefoon", aantal: 2, minimum: 2, locatie: "Kluis IT", laatstMutatie: "5 dagen" },
];

// ─── Devices ───────────────────────────────────────────────────────────────
export type DeviceStatus = "in-gebruik" | "voorraad" | "reparatie" | "afgeschreven";
export const deviceStatusLabels: Record<DeviceStatus, string> = {
  "in-gebruik": "In gebruik",
  voorraad: "Voorraad",
  reparatie: "Reparatie",
  afgeschreven: "Afgeschreven",
};
export interface Device {
  id: string;
  hostname: string;
  type: string;
  serie: string;
  medewerker: string | null;
  locatie: string;
  status: DeviceStatus;
  online: boolean;
  laatstGezien: string;
  os: string;
  ingezet: string;
}
export const devices: Device[] = [
  { id: "D-142", hostname: "LVP-LT-0142", type: "Dell Latitude 5450", serie: "SN-847291", medewerker: "Sanne de Vries", locatie: "Utrecht · kantoor", status: "in-gebruik", online: true, laatstGezien: "2 min geleden", os: "Windows 11 Pro 24H2", ingezet: "12 jan 2025" },
  { id: "D-141", hostname: "LVP-LT-0141", type: "Lenovo ThinkPad T14", serie: "SN-991234", medewerker: "Jeroen Bakker", locatie: "Utrecht · kantoor", status: "in-gebruik", online: true, laatstGezien: "nu online", os: "Windows 11 Pro 24H2", ingezet: "3 mrt 2024" },
  { id: "D-140", hostname: "LVP-LT-0140", type: "Dell Latitude 5450", serie: "SN-847290", medewerker: "Priya Ramautar", locatie: "Thuis · Amersfoort", status: "in-gebruik", online: false, laatstGezien: "gisteren 17:42", os: "Windows 11 Pro 24H2", ingezet: "8 apr 2025" },
  { id: "D-139", hostname: "LVP-LT-0139", type: "MacBook Pro 14\" M3", serie: "SN-C02XL", medewerker: "Ravi Sharma", locatie: "Thuis · Utrecht", status: "in-gebruik", online: true, laatstGezien: "nu online", os: "macOS 15.2", ingezet: "20 sep 2024" },
  { id: "D-138", hostname: "LVP-LT-0138", type: "Dell Latitude 5440", serie: "SN-847189", medewerker: null, locatie: "Magazijn A", status: "voorraad", online: false, laatstGezien: "n.v.t.", os: "Windows 11 Pro 24H2", ingezet: "—" },
  { id: "D-137", hostname: "LVP-LT-0137", type: "Lenovo ThinkPad T14", serie: "SN-991233", medewerker: null, locatie: "Reparatie · vendor", status: "reparatie", online: false, laatstGezien: "8 dagen geleden", os: "Windows 11 Pro 24H2", ingezet: "—" },
  { id: "D-136", hostname: "LVP-LT-0136", type: "Dell Latitude 5430", serie: "SN-847100", medewerker: null, locatie: "Recycling", status: "afgeschreven", online: false, laatstGezien: "1 mnd geleden", os: "—", ingezet: "—" },
];

// ─── Printers ──────────────────────────────────────────────────────────────
export interface Printer {
  id: string;
  naam: string;
  model: string;
  locatie: string;
  online: boolean;
  toner: { kleur: string; percent: number }[];
  paginas: number;
  laatstePrint: string;
  jobs: { tijd: string; gebruiker: string; document: string; paginas: number; status: "ok" | "fout" }[];
}
export const printers: Printer[] = [
  { id: "P-01", naam: "Marketing-4", model: "Canon iR-ADV C5560i", locatie: "1e verd. Marketing", online: false, toner: [{ kleur: "cyaan", percent: 12 }, { kleur: "magenta", percent: 47 }, { kleur: "geel", percent: 22 }, { kleur: "zwart", percent: 71 }], paginas: 148_293, laatstePrint: "8 uur geleden",
    jobs: [
      { tijd: "vandaag 09:14", gebruiker: "Tom Willems", document: "Campagne_zomer.pdf", paginas: 12, status: "fout" },
      { tijd: "gisteren 16:02", gebruiker: "Tom Willems", document: "Klantpresentatie.pptx", paginas: 24, status: "ok" },
      { tijd: "gisteren 11:47", gebruiker: "Karin Peters", document: "Personeelshandboek.docx", paginas: 88, status: "ok" },
    ]},
  { id: "P-02", naam: "Verkoop-2", model: "Canon iR-ADV C5540i", locatie: "1e verd. Verkoop", online: true, toner: [{ kleur: "cyaan", percent: 68 }, { kleur: "magenta", percent: 82 }, { kleur: "geel", percent: 55 }, { kleur: "zwart", percent: 91 }], paginas: 92_811, laatstePrint: "12 min geleden",
    jobs: [
      { tijd: "vandaag 10:12", gebruiker: "Bram Kooistra", document: "Offerte_VanElst_2026.pdf", paginas: 6, status: "ok" },
      { tijd: "vandaag 09:44", gebruiker: "Sanne de Vries", document: "Contract_KlantX.pdf", paginas: 14, status: "ok" },
    ]},
  { id: "P-03", naam: "HR-1", model: "HP Color LaserJet MFP", locatie: "BG HR", online: true, toner: [{ kleur: "cyaan", percent: 44 }, { kleur: "magenta", percent: 40 }, { kleur: "geel", percent: 39 }, { kleur: "zwart", percent: 62 }], paginas: 34_105, laatstePrint: "2 uur geleden",
    jobs: [
      { tijd: "vandaag 08:32", gebruiker: "Karin Peters", document: "Onboarding_pack.pdf", paginas: 22, status: "ok" },
    ]},
  { id: "P-04", naam: "Directie-1", model: "Canon iR-ADV C7580i", locatie: "3e verd.", online: true, toner: [{ kleur: "cyaan", percent: 88 }, { kleur: "magenta", percent: 90 }, { kleur: "geel", percent: 92 }, { kleur: "zwart", percent: 95 }], paginas: 8_244, laatstePrint: "1 dag geleden", jobs: [] },
];

// ─── Uitgifte / Hardware-uitgaven ─────────────────────────────────────────
export interface Uitgifte {
  id: string;
  datum: string;
  medewerker: string;
  afdeling: string;
  item: string;
  soort: "hardware" | "toegangspas" | "telefoon" | "overig";
  serienr?: string;
  geretourneerd: boolean;
  retourDatum?: string;
  uitgegevenDoor: string;
}
export const uitgiften: Uitgifte[] = [
  { id: "U-224", datum: "22 jul", medewerker: "Ravi Sharma", afdeling: "Development", item: "Dell UltraSharp 27\"", soort: "hardware", serienr: "SN-CN-88291", geretourneerd: false, uitgegevenDoor: "Mila van den Berg" },
  { id: "U-223", datum: "21 jul", medewerker: "Fleur Janssen", afdeling: "Financiën", item: "iPhone 15 128GB", soort: "telefoon", serienr: "IMEI 356789...", geretourneerd: false, uitgegevenDoor: "Mila van den Berg" },
  { id: "U-222", datum: "18 jul", medewerker: "Priya Ramautar", afdeling: "Consultancy", item: "Toegangspas + FOB", soort: "toegangspas", geretourneerd: false, uitgegevenDoor: "Karin Peters" },
  { id: "U-221", datum: "12 jul", medewerker: "Tom Willems", afdeling: "Marketing", item: "Logitech MX Master 3S", soort: "hardware", serienr: "SN-LOG-4429", geretourneerd: true, retourDatum: "20 jul", uitgegevenDoor: "Jeroen Bakker" },
  { id: "U-220", datum: "10 jul", medewerker: "Bram Kooistra", afdeling: "Verkoop", item: "Dell Latitude 5450", soort: "hardware", serienr: "SN-847288", geretourneerd: false, uitgegevenDoor: "Mila van den Berg" },
  { id: "U-219", datum: "5 jul", medewerker: "Ex-medewerker (Jan V.)", afdeling: "Verkoop", item: "Laptop + docking + telefoon", soort: "hardware", serienr: "meerdere", geretourneerd: true, retourDatum: "8 jul", uitgegevenDoor: "Mila van den Berg" },
  { id: "U-218", datum: "1 jul", medewerker: "Sanne de Vries", afdeling: "Verkoop", item: "Jabra Evolve2 65", soort: "hardware", serienr: "SN-JB-1128", geretourneerd: false, uitgegevenDoor: "Jeroen Bakker" },
];

// ─── CyberRisico ───────────────────────────────────────────────────────────
export type Risico = "laag" | "gemiddeld" | "hoog" | "kritiek";
export const risicoLabels: Record<Risico, string> = {
  laag: "Laag",
  gemiddeld: "Gemiddeld",
  hoog: "Hoog",
  kritiek: "Kritiek",
};
export interface CyberRisico {
  id: string;
  titel: string;
  categorie: string;
  eigenaar: string;
  kans: 1 | 2 | 3 | 4 | 5;
  impact: 1 | 2 | 3 | 4 | 5;
  niveau: Risico;
  status: "open" | "mitigatie" | "geaccepteerd" | "gesloten";
  aangemaakt: string;
  beschrijving: string;
}
export const risicos: CyberRisico[] = [
  { id: "CR-058", titel: "Ex-medewerkers behouden toegang tot SharePoint > 24u", categorie: "Toegangsbeheer", eigenaar: "Faisal El Amrani", kans: 4, impact: 4, niveau: "hoog", status: "mitigatie", aangemaakt: "14 jul", beschrijving: "HR-offboarding en IT-deprovisioning zijn niet gekoppeld. Handmatige stap loopt regelmatig 1–2 dagen achter." },
  { id: "CR-057", titel: "Onbeveiligde USB-sticks in gebruik bij consultants", categorie: "Data-uitwisseling", eigenaar: "Mila van den Berg", kans: 3, impact: 5, niveau: "kritiek", status: "open", aangemaakt: "10 jul", beschrijving: "Klantdata verlaat het bedrijf op niet-versleutelde sticks. Beleid en hardware ontbreken." },
  { id: "CR-056", titel: "PRD-SQL02 backup-retentie < 30 dagen", categorie: "Backup & recovery", eigenaar: "Faisal El Amrani", kans: 2, impact: 4, niveau: "hoog", status: "mitigatie", aangemaakt: "8 jul", beschrijving: "Compliance-eis is 90 dagen; retentie staat op 21." },
  { id: "CR-055", titel: "Phishing-training > 12 mnd geleden", categorie: "Awareness", eigenaar: "Karin Peters", kans: 4, impact: 3, niveau: "gemiddeld", status: "open", aangemaakt: "1 jul", beschrijving: "Verplichte jaarlijkse training niet gepland." },
  { id: "CR-054", titel: "MFA nog niet verplicht voor externe consultants", categorie: "Authenticatie", eigenaar: "Faisal El Amrani", kans: 3, impact: 4, niveau: "hoog", status: "open", aangemaakt: "28 jun", beschrijving: "Extern account 'externe@partner.nl' kan zonder MFA inloggen op SharePoint." },
  { id: "CR-053", titel: "Automatische schermvergrendeling ontbreekt op 3 kiosken", categorie: "Fysieke beveiliging", eigenaar: "Jeroen Bakker", kans: 3, impact: 2, niveau: "gemiddeld", status: "mitigatie", aangemaakt: "22 jun", beschrijving: "Receptie- en balie-schermen blijven onbeheerd aan." },
  { id: "CR-052", titel: "Oude firmware Fortinet-firewall", categorie: "Infrastructuur", eigenaar: "Faisal El Amrani", kans: 2, impact: 3, niveau: "gemiddeld", status: "gesloten", aangemaakt: "10 jun", beschrijving: "Bijgewerkt naar 7.4.6." },
  { id: "CR-051", titel: "Wachtwoordbeleid staat op 8 tekens", categorie: "Authenticatie", eigenaar: "Faisal El Amrani", kans: 4, impact: 3, niveau: "gemiddeld", status: "mitigatie", aangemaakt: "5 jun", beschrijving: "NIST-aanbeveling is 12+; roll-out gepland Q3." },
];

// ─── Medewerkers ───────────────────────────────────────────────────────────
export interface Medewerker {
  id: string;
  naam: string;
  initialen: string;
  rol: string;
  afdeling: string;
  team: string;
  manager: string | null;
  keyuser: boolean;
  email: string;
  telefoon: string;
  toestel: string;
  status: "actief" | "verlof" | "uit-dienst";
  inBehandeling: number;
}
export const medewerkers: Medewerker[] = [
  { id: "MW-01", naam: "Ilse Groen", initialen: "IG", rol: "Directeur", afdeling: "Directie", team: "MT", manager: null, keyuser: true, email: "i.groen@vanpunt.nl", telefoon: "06-12345601", toestel: "101", status: "actief", inBehandeling: 0 },
  { id: "MW-02", naam: "Mila van den Berg", initialen: "MB", rol: "Support · Keyuser", afdeling: "IT", team: "Support", manager: "Ilse Groen", keyuser: true, email: "m.vdberg@vanpunt.nl", telefoon: "06-12345602", toestel: "210", status: "actief", inBehandeling: 5 },
  { id: "MW-03", naam: "Jeroen Bakker", initialen: "JB", rol: "Support-medewerker", afdeling: "IT", team: "Support", manager: "Mila van den Berg", keyuser: false, email: "j.bakker@vanpunt.nl", telefoon: "06-12345603", toestel: "211", status: "actief", inBehandeling: 4 },
  { id: "MW-04", naam: "Faisal El Amrani", initialen: "FE", rol: "Systeembeheerder", afdeling: "IT", team: "Infra", manager: "Mila van den Berg", keyuser: true, email: "f.elamrani@vanpunt.nl", telefoon: "06-12345604", toestel: "212", status: "actief", inBehandeling: 3 },
  { id: "MW-05", naam: "Karin Peters", initialen: "KP", rol: "HR-adviseur", afdeling: "HR", team: "HR", manager: "Ilse Groen", keyuser: false, email: "k.peters@vanpunt.nl", telefoon: "06-12345605", toestel: "301", status: "actief", inBehandeling: 1 },
  { id: "MW-06", naam: "Bram Kooistra", initialen: "BK", rol: "Accountmanager", afdeling: "Verkoop", team: "Sales NL", manager: "Ilse Groen", keyuser: false, email: "b.kooistra@vanpunt.nl", telefoon: "06-12345606", toestel: "410", status: "actief", inBehandeling: 0 },
  { id: "MW-07", naam: "Sanne de Vries", initialen: "SV", rol: "Binnendienst verkoop", afdeling: "Verkoop", team: "Sales NL", manager: "Bram Kooistra", keyuser: false, email: "s.devries@vanpunt.nl", telefoon: "06-12345607", toestel: "411", status: "actief", inBehandeling: 0 },
  { id: "MW-08", naam: "Tom Willems", initialen: "TW", rol: "Marketeer", afdeling: "Marketing", team: "Content", manager: "Ilse Groen", keyuser: false, email: "t.willems@vanpunt.nl", telefoon: "06-12345608", toestel: "501", status: "actief", inBehandeling: 0 },
  { id: "MW-09", naam: "Ravi Sharma", initialen: "RS", rol: "Frontend developer", afdeling: "Development", team: "Product", manager: "Faisal El Amrani", keyuser: false, email: "r.sharma@vanpunt.nl", telefoon: "06-12345609", toestel: "601", status: "actief", inBehandeling: 0 },
  { id: "MW-10", naam: "Priya Ramautar", initialen: "PR", rol: "Consultant", afdeling: "Consultancy", team: "CRM-consultancy", manager: "Ilse Groen", keyuser: true, email: "p.ramautar@vanpunt.nl", telefoon: "06-12345610", toestel: "701", status: "verlof", inBehandeling: 0 },
  { id: "MW-11", naam: "Fleur Janssen", initialen: "FJ", rol: "Financieel medewerker", afdeling: "Financiën", team: "Finance", manager: "Ilse Groen", keyuser: false, email: "f.janssen@vanpunt.nl", telefoon: "06-12345611", toestel: "801", status: "actief", inBehandeling: 0 },
];

// ─── Agenda ────────────────────────────────────────────────────────────────
export interface AgendaEvent {
  dag: number; // 0-4 (ma-vr)
  startUur: number; // 8-18
  duur: number; // uren
  medewerker: string;
  titel: string;
  ticket?: string;
  tone: "behandeling" | "wachtend" | "open" | "opgelost";
}
export const agendaEvents: AgendaEvent[] = [
  { dag: 0, startUur: 9, duur: 1, medewerker: "Jeroen Bakker", titel: "OST-herbouw op locatie Sanne", ticket: "T-2841", tone: "behandeling" },
  { dag: 0, startUur: 10, duur: 2, medewerker: "Mila van den Berg", titel: "Onboarding nieuwe collega", ticket: "T-2840", tone: "open" },
  { dag: 0, startUur: 13, duur: 3, medewerker: "Faisal El Amrani", titel: "Fortinet firmware upgrade", tone: "behandeling" },
  { dag: 1, startUur: 9, duur: 2, medewerker: "Jeroen Bakker", titel: "Teams-issue Ilse", ticket: "T-2834", tone: "behandeling" },
  { dag: 1, startUur: 11, duur: 1, medewerker: "Mila van den Berg", titel: "CRM klantkaart Van Elst", ticket: "T-2837", tone: "open" },
  { dag: 1, startUur: 14, duur: 2, medewerker: "Faisal El Amrani", titel: "VPN-issue Priya", ticket: "T-2838", tone: "behandeling" },
  { dag: 2, startUur: 10, duur: 1, medewerker: "Jeroen Bakker", titel: "Printer Marketing-4", ticket: "T-2839", tone: "wachtend" },
  { dag: 2, startUur: 13, duur: 2, medewerker: "Mila van den Berg", titel: "KB-review MailMind concepts", tone: "behandeling" },
  { dag: 3, startUur: 9, duur: 4, medewerker: "Faisal El Amrani", titel: "Backup-retentie herconfiguratie", tone: "behandeling" },
  { dag: 3, startUur: 14, duur: 1, medewerker: "Jeroen Bakker", titel: "Hardware-uitgifte Ravi", tone: "opgelost" },
  { dag: 4, startUur: 10, duur: 2, medewerker: "Mila van den Berg", titel: "Wekelijkse teamreflectie", tone: "opgelost" },
];

// ─── Telefoonlijst ─────────────────────────────────────────────────────────
export const telefoonlijst = medewerkers.map((m) => ({
  naam: m.naam,
  toestel: m.toestel,
  mobiel: m.telefoon,
  afdeling: m.afdeling,
  email: m.email,
}));

// ─── Scripts ───────────────────────────────────────────────────────────────
export interface ScriptItem {
  id: string;
  naam: string;
  taal: "PowerShell" | "Bash" | "Python" | "SQL";
  eigenaar: string;
  laatstUitgevoerd: string;
  status: "ok" | "fout" | "nooit";
  inhoud: string;
  beschrijving: string;
}
export const scripts: ScriptItem[] = [
  { id: "SC-014", naam: "Reset-OSTProfile.ps1", taal: "PowerShell", eigenaar: "Faisal El Amrani", laatstUitgevoerd: "vandaag 09:12", status: "ok", beschrijving: "Sluit Outlook, hernoemt corrupt OST-bestand en start Outlook opnieuw.",
    inhoud: `# Reset-OSTProfile.ps1
# Ondersteunt VP-034: automatische herbouw van Outlook-profiel.

param([string]$User = $env:USERNAME)

Stop-Process -Name "OUTLOOK" -Force -ErrorAction SilentlyContinue
$ost = "$env:LOCALAPPDATA\\Microsoft\\Outlook\\*.ost"
Get-ChildItem $ost | ForEach-Object {
  Rename-Item $_.FullName ($_.FullName + ".bak")
}
Start-Process "outlook.exe"
Write-Host "OST-profiel voor $User opnieuw opgebouwd."` },
  { id: "SC-013", naam: "sync-user-roles.sh", taal: "Bash", eigenaar: "Faisal El Amrani", laatstUitgevoerd: "gisteren 22:00", status: "ok", beschrijving: "Synchroniseert rollen vanuit HR-CSV naar Active Directory.",
    inhoud: `#!/usr/bin/env bash
set -euo pipefail
CSV="/mnt/hr/exports/roles_$(date +%F).csv"
[ -f "$CSV" ] || { echo "Geen export gevonden"; exit 1; }
python3 /opt/scripts/sync_ad.py --input "$CSV" --dry-run false` },
  { id: "SC-012", naam: "check_disk_usage.py", taal: "Python", eigenaar: "Faisal El Amrani", laatstUitgevoerd: "vandaag 06:00", status: "fout", beschrijving: "Rapporteert volumes boven 85% aan MailMind-alerting.",
    inhoud: `import shutil, json, socket
volumes = ["/", "/var", "/data"]
report = []
for v in volumes:
    total, used, free = shutil.disk_usage(v)
    report.append({"volume": v, "pct": round(used/total*100, 1)})
print(json.dumps({"host": socket.gethostname(), "volumes": report}))` },
  { id: "SC-011", naam: "close_stale_tickets.sql", taal: "SQL", eigenaar: "Mila van den Berg", laatstUitgevoerd: "1 week", status: "ok", beschrijving: "Sluit tickets die > 30 dagen op 'opgelost' staan zonder reactie.",
    inhoud: `UPDATE tickets
SET status = 'gesloten', gesloten_op = NOW()
WHERE status = 'opgelost'
  AND laatst_bijgewerkt < NOW() - INTERVAL '30 days';` },
  { id: "SC-010", naam: "Rotate-ApiKeys.ps1", taal: "PowerShell", eigenaar: "Faisal El Amrani", laatstUitgevoerd: "nooit", status: "nooit", beschrijving: "Roteert API-sleutels ouder dan 180 dagen (concept).", inhoud: `# TODO: implementatie` },
];

// ─── Schijfgebruik ─────────────────────────────────────────────────────────
export interface Volume {
  server: string;
  volume: string;
  totaalGB: number;
  gebruiktGB: number;
  type: "SSD" | "HDD" | "NAS";
}
export const schijfVolumes: Volume[] = [
  { server: "PRD-FS01", volume: "D:\\Data", totaalGB: 4000, gebruiktGB: 3760, type: "NAS" },
  { server: "PRD-SQL02", volume: "E:\\SQLData", totaalGB: 2000, gebruiktGB: 1810, type: "SSD" },
  { server: "PRD-FS01", volume: "F:\\Backups", totaalGB: 8000, gebruiktGB: 5920, type: "HDD" },
  { server: "PRD-APP01", volume: "C:\\", totaalGB: 500, gebruiktGB: 322, type: "SSD" },
  { server: "PRD-DC01", volume: "C:\\", totaalGB: 250, gebruiktGB: 118, type: "SSD" },
  { server: "PRD-EXCH01", volume: "M:\\Mail", totaalGB: 3000, gebruiktGB: 1420, type: "SSD" },
  { server: "PRD-MEDIA", volume: "V:\\Video", totaalGB: 12000, gebruiktGB: 2810, type: "HDD" },
];

// ─── Beheer ────────────────────────────────────────────────────────────────
export const beheerUsers = [
  { naam: "Ilse Groen", email: "i.groen@vanpunt.nl", rollen: ["Directie"], mfa: true, laatstIngelogd: "vandaag" },
  { naam: "Mila van den Berg", email: "m.vdberg@vanpunt.nl", rollen: ["Support-admin", "Keyuser"], mfa: true, laatstIngelogd: "nu online" },
  { naam: "Faisal El Amrani", email: "f.elamrani@vanpunt.nl", rollen: ["Systeembeheerder", "Keyuser"], mfa: true, laatstIngelogd: "nu online" },
  { naam: "Jeroen Bakker", email: "j.bakker@vanpunt.nl", rollen: ["Support"], mfa: true, laatstIngelogd: "vandaag" },
  { naam: "Karin Peters", email: "k.peters@vanpunt.nl", rollen: ["HR"], mfa: false, laatstIngelogd: "gisteren" },
  { naam: "Bram Kooistra", email: "b.kooistra@vanpunt.nl", rollen: ["Verkoop"], mfa: true, laatstIngelogd: "vandaag" },
];

export const rollen = ["Directie", "Systeembeheerder", "Support-admin", "Support", "Keyuser", "HR", "Verkoop", "Marketing", "Consultancy", "Development", "Financiën"];
export const rechten = [
  { naam: "Tickets · alles", matrix: [true, true, true, true, true, false, false, false, false, false, false] },
  { naam: "Tickets · eigen team", matrix: [true, true, true, true, true, true, true, true, true, true, true] },
  { naam: "Kennisbank · bewerken", matrix: [true, true, true, true, true, false, false, false, false, false, false] },
  { naam: "Medewerkers · bewerken", matrix: [true, true, true, false, false, true, false, false, false, false, false] },
  { naam: "CyberRisico · lezen", matrix: [true, true, true, false, true, false, false, false, false, false, false] },
  { naam: "Beheer · gebruikers", matrix: [true, true, true, false, false, false, false, false, false, false, false] },
  { naam: "Scripts · uitvoeren", matrix: [false, true, false, false, false, false, false, false, false, false, false] },
];

export const apiKeys = [
  { naam: "MailMind → Kennisbank", key: "sk_live_mm_kb_a1b2••••••••", scope: ["kb:write", "mail:read"], gemaakt: "12 mrt 2025", laatstGebruikt: "2 min geleden", door: "Faisal El Amrani" },
  { naam: "Monitoring webhook", key: "sk_live_mon_c3d4••••••••", scope: ["tickets:create"], gemaakt: "5 feb 2025", laatstGebruikt: "vandaag 06:00", door: "Faisal El Amrani" },
  { naam: "HR-sync (Nmbrs)", key: "sk_live_hr_e5f6••••••••", scope: ["medewerkers:read", "medewerkers:write"], gemaakt: "20 jan 2025", laatstGebruikt: "gisteren 22:00", door: "Mila van den Berg" },
  { naam: "CRM readonly export", key: "sk_live_crm_g7h8••••••••", scope: ["crm:read"], gemaakt: "1 nov 2024", laatstGebruikt: "1 maand geleden", door: "Bram Kooistra" },
];

export const mailQueue = [
  { id: "MQ-9021", ontvanger: "s.devries@vanpunt.nl", onderwerp: "Re: Outlook OST", status: "verzonden", tijd: "12 min geleden" },
  { id: "MQ-9020", ontvanger: "hr@vanpunt.nl", onderwerp: "Onboarding checklist bevestigd", status: "verzonden", tijd: "38 min geleden" },
  { id: "MQ-9019", ontvanger: "n.kooi@klant-relatie.nl", onderwerp: "MailMind — automatisch antwoord", status: "wachtrij", tijd: "1 uur geleden" },
  { id: "MQ-9018", ontvanger: "monitoring@vanpunt.nl", onderwerp: "Alert: schijf > 85%", status: "fout", tijd: "1 uur geleden" },
  { id: "MQ-9017", ontvanger: "b.kooistra@vanpunt.nl", onderwerp: "CRM klantkaart bijgewerkt", status: "verzonden", tijd: "3 uur geleden" },
] as const;

export const systeemLogs = [
  { tijd: "10:15:22", niveau: "info", bron: "tickets", bericht: "T-2841 status → behandeling (Jeroen Bakker)" },
  { tijd: "10:12:04", niveau: "info", bron: "auth", bericht: "Login m.vdberg@vanpunt.nl vanaf 192.168.10.42" },
  { tijd: "10:03:18", niveau: "info", bron: "mailmind", bericht: "KB-artikel gepubliceerd uit M-8815" },
  { tijd: "09:48:52", niveau: "warn", bron: "backup", bericht: "PRD-SQL02 backup duurde 92 min (drempel 60)" },
  { tijd: "09:22:11", niveau: "info", bron: "tickets", bericht: "T-2841 toegewezen aan Jeroen Bakker" },
  { tijd: "09:14:07", niveau: "info", bron: "mailmind", bericht: "Nieuwe mail geclassificeerd als E-mail/Exchange (92%)" },
  { tijd: "06:00:03", niveau: "error", bron: "scripts", bericht: "check_disk_usage.py exit=1 op PRD-FS01" },
  { tijd: "06:00:00", niveau: "info", bron: "scripts", bericht: "Nightly run gestart (5 scripts)" },
];
