<?php
/**
 * Gemini AI Chat Proxy
 * PHP proxy goi Google Gemini API cho Tro ly ao Y te
 * Tach biet hoan toan voi AI Server (GNN predictions)
 */
require_once __DIR__ . '/../includes/config.php';

// ============ GEMINI API KEY ============
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'YOUR_GEMINI_API_KEY_HERE');

if (GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
    error_log("WARNING: Gemini API Key chua duoc cau hinh. Su dung che do Demo.");
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];

if (empty($message)) {
    jsonResponse(['error' => 'Tin nhan trong'], 400);
}

if (GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
    $demoResponse = getDemoResponse($message);
    jsonResponse(['reply' => $demoResponse, 'mode' => 'demo']);
}

// ============ GOI GEMINI API ============
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY;

$systemInstruction = "Ban la Tro ly Y te AI chuyen nghiep ten MedBot, duoc tich hop trong he thong du doan lien ket Thuoc-Benh AMDGT. " .
    "Nhiem vu cua ban:\n" .
    "1. Tra loi cac cau hoi ve thuoc: tac dung phu, lieu dung, tuong tac thuoc, co che hoat dong.\n" .
    "2. Tra loi cac cau hoi ve benh: trieu chung, nguyen nhan, phuong phap dieu tri.\n" .
    "3. Giai thich cac khai niem y khoa mot cach de hieu.\n" .
    "4. Luon nhac nho nguoi dung tham khao y kien bac si truoc khi dung thuoc.\n" .
    "5. Tra loi bang tieng Viet, ngan gon, de hieu (toi da 200 tu).\n" .
    "6. Su dung emoji phu hop cho sinh dong.\n" .
    "7. Neu cau hoi khong lien quan den y te, nhe nhang tu choi va huong dan hoi ve y te.\n" .
    "LUU Y QUAN TRONG: Ban KHONG phai bac si. Luon khuyen nguoi dung hoi bac si chuyen khoa.";

$contents = [];

foreach ($history as $msg) {
    $contents[] = [
        'role' => $msg['role'] === 'user' ? 'user' : 'model',
        'parts' => [['text' => $msg['text']]]
    ];
}

$contents[] = [
    'role' => 'user',
    'parts' => [['text' => $message]]
];

$requestBody = [
    'system_instruction' => [
        'parts' => [['text' => $systemInstruction]]
    ],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.7,
        'topP' => 0.9,
        'maxOutputTokens' => 800
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    jsonResponse(['error' => 'Loi ket noi Gemini API: ' . $curlError], 500);
}

$data = json_decode($response, true);

if ($httpCode !== 200 || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
    $demoResponse = getDemoResponse($message);
    jsonResponse(['reply' => $demoResponse, 'mode' => 'demo_fallback']);
}

$reply = $data['candidates'][0]['content']['parts'][0]['text'];
jsonResponse(['reply' => $reply, 'mode' => 'gemini']);


// ============ DEMO RESPONSES (Offline Mode) ============
function getDemoResponse($message) {
    $msg = mb_strtolower(trim($message), 'UTF-8');
    
    // === DRUG-SPECIFIC RESPONSES ===
    $drugResponses = [
        'aspirin' => [
            'name' => 'Aspirin',
            'effects' => "- Dau da day, buon non, o nach\n- Xuat huyet tieu hoa (dung lau dai)\n- U tai (lieu cao)\n- Phan ung di ung (hiem gap)\n- Tang nguy co chay mau",
            'interactions' => "- Warfarin: Tang nguy co chay mau\n- Ibuprofen: Giam tac dung Aspirin\n- Thuoc huyet ap: Co the giam hieu qua",
            'note' => "Khong dung cho tre < 16 tuoi (nguy co hoi chung Reye)"
        ],
        'metformin' => [
            'name' => 'Metformin',
            'effects' => "- Buon non, tieu chay (ban dau)\n- Dau bung, chan an\n- Thieu Vitamin B12 (dung lau dai)\n- Lactic acidosis (hiem nhung nguy hiem)",
            'interactions' => "- Ruou: Tang nguy co lactic acidosis\n- Thuoc can quang: Can ngung truoc X-quang\n- Thuoc suy tim: Tuong tac can theo doi",
            'note' => "Thuoc dieu tri tieu duong type 2. Uong khi an de giam tac dung phu"
        ],
        'paracetamol' => [
            'name' => 'Paracetamol',
            'effects' => "- Thong thuong an toan o lieu dieu tri\n- Buon non nhe, dau bung (hiem)\n- Ton thuong gan nghiem trong neu qua lieu (>4g/ngay)\n- Phan ung da (rat hiem)",
            'interactions' => "- Ruou: Tang nguy co ton thuong gan\n- Warfarin: Co the tang nguy co chay mau",
            'note' => "Khong dung qua 4g/ngay. Tranh dung cung ruou bia"
        ],
        'ibuprofen' => [
            'name' => 'Ibuprofen',
            'effects' => "- Dau da day, buon non\n- Nhuc dau, chong mat\n- Tang huyet ap\n- Nguy co loet da day\n- Ton thuong than (dung lau dai)",
            'interactions' => "- Aspirin: Giam tac dung bao ve tim\n- Thuoc huyet ap: Giam hieu qua\n- Thuoc chong dong: Tang nguy co chay mau",
            'note' => "Uong voi thuc an de giam kich ung da day"
        ],
        'omeprazole' => [
            'name' => 'Omeprazole',
            'effects' => "- Dau dau, tieu chay\n- Buon non, dau bung\n- Giam hap thu Vitamin B12\n- Nguy co gay xuong (dung lau dai)\n- Nhiem Clostridium difficile",
            'interactions' => "- Clopidogrel: Giam hieu qua thuoc\n- Methotrexate: Tang nong do\n- Thuoc HIV: Tuong tac nghiem trong",
            'note' => "Thuoc uc che bom proton (PPI). Khong nen dung > 8 tuan khong co chi dinh"
        ],
        'atorvastatin' => [
            'name' => 'Atorvastatin',
            'effects' => "- Dau co, yeu co\n- Tang men gan (it gap)\n- Dau dau, chong mat\n- Buon non, tieu chay\n- Nguy co tieu duong type 2 (hiem)",
            'interactions' => "- Nuoc buoi: Tang nong do thuoc\n- Erythromycin: Tang nguy co tieu co\n- Simvastatin: Tang nguy co tieu co",
            'note' => "Statin giam cholesterol. Tranh tap the duc nang khi co dau co"
        ],
        'amlodipine' => [
            'name' => 'Amlodipine',
            'effects' => "- Pho chan, mat cho chan\n- Do bung mat\n- Chong mat, nhuc dau\n- Met moi\n- Dau nguc (it gap)",
            'interactions' => "- Simvastatin: Tang nguy co tieu co\n- Thuoc huyet ap khac: Ha huyet ap qua muc\n- Cyclosporine: Tang nong do",
            'note' => "Thuoc Ha ap nhom Calcium channel blocker. Uong vao cung gio moi ngay"
        ],
        'lisinopril' => [
            'name' => 'Lisinopril',
            'effects' => "- Ho khan keo dai\n- Chong mat, dau dau\n- Tang kali mau\n- Pho nguy (hiem nhung nguy hiem)",
            'interactions' => "- Thuoc loi tieu: Ha huyet ap qua muc\n- Potassium: Tang nguy co tang kali mau\n- NSAID: Giam hieu qua, ton thuong than",
            'note' => "Thuoc uc che men chuyen (ACEI). Uong khi nao de hap thu tot hon"
        ]
    ];
    
    // Check for specific drug mentions
    foreach ($drugResponses as $key => $drug) {
        if (strpos($msg, $key) !== false) {
            if (strpos($msg, 'tac dung phu') !== false || strpos($msg, 'side effect') !== false) {
                return "Thuoc **" . $drug['name'] . ":**\n\n" . $drug['effects'] . "\n\n" . $drug['note'] . "\n\nCan than: Hay tham khao y kien bac si!";
            }
            
            if (strpos($msg, 'tuong tac') !== false || strpos($msg, 'interaction') !== false) {
                return "Tuong tac thuoc cua **" . $drug['name'] . ":**\n\n" . $drug['interactions'] . "\n\nCan than: Thong bao cho bac si tat ca thuoc ban dang dung!";
            }
            
            return "**" . $drug['name'] . "**\n\nTac dung phu thuong gap:\n" . $drug['effects'] . "\n\nLuu y:\n" . $drug['note'] . "\n\nBan muon hoi them ve " . $drug['name'] . " khong?\n\nCan than: Hay tham khao y kien bac si!";
        }
    }
    
    // === GENERAL MEDICAL RESPONSES ===
    
    // Tac dung phu (generic)
    if (strpos($msg, 'tac dung phu') !== false || strpos($msg, 'side effect') !== false) {
        return "De tra loi chinh xac ve tac dung phu, ban hay cho toi biet ten thuoc cu the nhe!\n\n" .
            "VD: \"Tac dung phu cua Aspirin la gi?\"\n\n" .
            "Mot so thuoc pho bien de hoi:\n" .
            "- Aspirin - Giam dau, chong vien\n" .
            "- Paracetamol - Ha sot, giam dau\n" .
            "- Metformin - Dieu tri tieu duong\n" .
            "- Ibuprofen - Giam dau, chong vien\n\n" .
            "Can than: Day la AI tro ly, khong phai bac si. Hay tham khao y kien chuyen gia y te!";
    }
    
    // Tuong tac thuoc
    if (strpos($msg, 'tuong tac') !== false || strpos($msg, 'interaction') !== false) {
        return "Tuong tac thuoc la khi hai hay nhieu thuoc dung cung luc gay ra tac dung khac biet.\n\n" .
            "Cac loai tuong tac:\n" .
            "- Dong khang: thuoc triet tieu tac dung cua nhau\n" .
            "- Hiep dong: tang tac dung (co the nguy hiem)\n" .
            "- Bo sung: ket hop tot cho dieu tri\n\n" .
            "Mot so tuong tac nguy hiem:\n" .
            "- Warfarin + Aspirin: Tang nguy co chay mau\n" .
            "- Statin + Nuoc buoi: Tang nguy co tieu co\n" .
            "- ACEI + Potassium: Tang nguy co tang kali mau\n\n" .
            "Hay su dung tinh nang Du doan cua he thong AMDGT de kiem tra!\n\n" .
            "Can than: Hay thong bao cho bac si tat ca thuoc ban dang dung!";
    }
    
    // Lieu dung
    if (strpos($msg, 'lieu') !== false || strpos($msg, 'dose') !== false || strpos($msg, 'uong') !== false) {
        return "Lieu dung thuoc phu thuoc vao nhieu yeu to:\n\n" .
            "- Tuoi, can nang, gioi tinh\n" .
            "- Tinh trang benh ly\n" .
            "- Cac thuoc dang dung kem\n" .
            "- Chuc nang gan, than\n\n" .
            "Lieu thong thuong mot so thuoc pho bien:\n" .
            "- Paracetamol: 500mg - 1g/lan, toi da 4g/ngay\n" .
            "- Ibuprofen: 200-400mg/lan, toi da 1.2g/ngay\n" .
            "- Aspirin: 75-325mg/ngay (phong ngua tim mach)\n\n" .
            "Ban can hoi bac si hoac duoc si de duoc tu van lieu dung chinh xac nhe!\n\n" .
            "Can than: Khong tu y thay doi lieu dung!";
    }
    
    // Co che hoat dong
    if (strpos($msg, 'co che') !== false || strpos($msg, 'mechanism') !== false) {
        return "Co che hoat dong cua thuoc:\n\n" .
            "Thuoc tac dong len co the theo nhieu cach:\n\n" .
            "1. Tac dong len thu the (Receptors)\n" .
            "- Thuoc doi khang: Chan thu the, ngan chat gay benh\n" .
            "- Thuoc agonist: Kich hoat thu the, tao phan ung\n\n" .
            "2. Uc che enzyme\n" .
            "- Ngan chan phan ung hoa hoc trong co the\n" .
            "- VD: ACEI uc che men chuyen, giam huyet ap\n\n" .
            "3. Thay the chat trong co the\n" .
            "- VD: Insulin cho nguoi tieu duong\n\n" .
            "4. Tieu diet vi sinh vat\n" .
            "- Khang sinh diet vi khuan\n\n" .
            "Can than: Co che phuc tap. Hoi bac si de hieu ro hon!";
    }
    
    // Trieu chung benh
    if (strpos($msg, 'trieu chung') !== false || strpos($msg, 'symptom') !== false) {
        return "Trieu chung benh ly pho bien:\n\n" .
            "Ban dang hoi ve trieu chung cua benh nao?\n\n" .
            "Mot so benh pho bien:\n" .
            "- Cam cum: Sot, nhuc dau, met moi, dau hong, ho\n" .
            "- Dau da day: Dau thuong vi, buon non, o chua\n" .
            "- Tieu duong: Khat nhieu, di tiểu nhieu, met moi\n" .
            "- Cao huyet ap: Dau dau, chong mat, met moi (thuong khong co trieu chung)\n" .
            "- Hen suyen: Kho tho, tho khokhete, ho\n\n" .
            "Hay nhap ten benh cu the de toi giup ban nhe!\n\n" .
            "Can than: Neu co trieu chung nghiem trong, hay den gap bac si ngay!";
    }
    
    // Protein - Drug Interaction
    if (strpos($msg, 'protein') !== false && (strpos($msg, 'thuoc') !== false || strpos($msg, 'drug') !== false)) {
        return "Tuong tac Protein-Thuoc:\n\n" .
            "Protein dong vai tro quan trong trong tac dung cua thuoc:\n\n" .
            "1. Enzyme metabolizing thuoc (CYP450)\n" .
            "- CYP3A4: Phan huy ~50% thuoc\n" .
            "- CYP2D6: Metabolize nhieu thuoc tam than\n" .
            "- Thuoc co the uc che hoac kich thich enzyme\n\n" .
            "2. Protein van chuyen\n" .
            "- P-glycoprotein: Day thuoc ra khoi te bao\n" .
            "- Anh huong hap thu va phan bo thuoc\n\n" .
            "3. Thu the (Receptors)\n" .
            "- Protein gan thuoc, truyen tin hieu\n" .
            "- Quyet dinh hieu qua dieu tri\n\n" .
            "He thong AMDGT co the du doan cac tuong tac nay!\n\n" .
            "Can that: Tuong tac phuc tap. Hoi bac si!";
    }
    
    // Persistent Homology / PH
    if (strpos($msg, 'persistent homology') !== false || strpos($msg, 'do thi') !== false || strpos($msg, 'topology') !== false) {
        return "Persistent Homology trong Du doan Thuoc-Benh:\n\n" .
            "Day la ky thuat toan hoc cao cap dung trong he thong AMDGT:\n\n" .
            "Y tuong co ban:\n" .
            "- Xem mang luoi thuoc-benh nhu mot do thi\n" .
            "- PH phat hien cac cau truc (vong, lo) ben vung\n" .
            "- Cau truc ben vung = Thong tin quan trong\n\n" .
            "Ung dung:\n" .
            "- Trich xuat dac trung tu mang phuc tap\n" .
            "- Cai thien do chinh xac du doan\n" .
            "- Phat hien moi lien he tiem an\n\n" .
            "He thong AMDGT su dung PH + GNN de dat hieu qua cao!\n\n" .
            "Ban muon tim hieu them ve cach hoat dong khong?";
    }
    
    // GNN / Neural Network
    if (strpos($msg, 'neural') !== false || strpos($msg, 'mang noron') !== false || strpos($msg, 'machine learning') !== false || strpos($msg, 'ai') !== false) {
        return "Tri tue nhan tao trong Du doan Thuoc-Benh:\n\n" .
            "Graph Neural Network (GNN) la cong nghe AI chinh:\n\n" .
            "Cach hoat dong:\n" .
            "1. Dua vao du lieu: Thuoc, Benh, Protein\n" .
            "2. Xay dung do thi: Cac moi lien ket\n" .
            "3. Mang noron hoc tu cau truc do thi\n" .
            "4. Du doan: Lien ket thuoc-benh moi\n\n" .
            "Uu diem cua GNN:\n" .
            "- Hieu duoc cau truc phuc tap cua mang sinh hoc\n" .
            "- Khám pha moi lien he khong ro rang\n" .
            "- Do chinh xac cao hon phuong phap truyen thong\n\n" .
            "AMDGT ket hop GNN + Persistent Homology de dat ket qua tot nhat!\n\n" .
            "Can than: AI ho tro nhung khong thay the bac si!";
    }
    
    // Drug-Disease Prediction
    if (strpos($msg, 'du doan') !== false && (strpos($msg, 'thuoc') !== false || strpos($msg, 'benh') !== false)) {
        return "Du doan Lien ket Thuoc-Benh:\n\n" .
            "He thong AMDGT co the giup ban:\n\n" .
            "1. Tim thuoc cho benh\n" .
            "- Nhap ten benh\n" .
            "- AI de xuat cac thuoc tiem nang\n" .
            "- Xep hang theo kha nang dieu tri\n\n" .
            "2. Tim benh cho thuoc\n" .
            "- Nhap ten thuoc\n" .
            "- AI goi y cac benh co the dieu tri\n" .
            "- Phat hien cong dung moi\n\n" .
            "3. Phan tich Protein trung gian\n" .
            "- Tim pathway ket noi thuoc va benh\n" .
            "- Hieu co che tac dung\n\n" .
            "Hay vao trang Du doan de trai nghiem!\n\n" .
            "Can than: Ket qua la tham khao, can xac nhan lam sang!";
    }
    
    // Greeting
    if (strpos($msg, 'xin chao') !== false || strpos($msg, 'hello') !== false || strpos($msg, 'hi') !== false || strpos($msg, 'chao') !== false || $msg === 'bot' || $msg === 'medbot') {
        return "Xin chao! Toi la **MedBot** - Tro ly AI Y te cua he thong AMDGT!\n\n" .
            "Toi co the giup ban:\n\n" .
            "- Ve thuoc: Tac dung phu, tuong tac, lieu dung, co che\n" .
            "- Ve benh: Trieu chung, nguyen nhan, dieu tri\n" .
            "- Ve cong nghe: GNN, Persistent Homology, AI\n" .
            "- Ve he thong: Cach su dung AMDGT\n\n" .
            "Vi du cau hoi:\n" .
            "- \"Tac dung phu cua Aspirin?\"\n" .
            "- \"Tuong tac thuoc la gi?\"\n" .
            "- \"GNN hoat dong nhu the nao?\"\n\n" .
            "Hay hoi toi bat cu dieu gi! \n\n" .
            "Can that: Toi la AI tro ly, khong phai bac si. Hay tham khao y kien chuyen gia y te!";
    }
    
    // Help
    if (strpos($msg, 'giup') !== false || strpos($msg, 'help') !== false || strpos($msg, 'huong dan') !== false) {
        return "Huong dan su dung MedBot:\n\n" .
            "Toi co the tra loi cac cau hoi ve:\n\n" .
            "Thuoc & Tac dung:\n" .
            "- Tac dung phu cua [ten thuoc]\n" .
            "- Tuong tac cua [thuoc 1] voi [thuoc 2]\n" .
            "- Lieu dung [thuoc]\n\n" .
            "Benh ly:\n" .
            "- Trieu chung [benh]\n" .
            "- Cach dieu tri [benh]\n\n" .
            "Cong nghe Y sinh:\n" .
            "- GNN la gi?\n" .
            "- Persistent Homology hoat dong the nao?\n" .
            "- AI du doan thuoc-benh ra sao?\n\n" .
            "AMDGT:\n" .
            "- Cach su dung he thong du doan\n" .
            "- Dataset la gi?\n\n" .
            "Hay hoi toi nhe!";
    }
    
    // Side effects warning
    if (strpos($msg, 'nguy hiem') !== false || strpos($msg, 'canh bao') !== false || strpos($msg, 'warning') !== false) {
        return "Canh bao quan trong ve thuoc:\n\n" .
            "Khi nao can goi cap cuu?\n" .
            "- Kho tho, phu mach (sung mat, moi)\n" .
            "- Chay mau khong cam duoc\n" .
            "- Dau nguc duoi doi\n" .
            "- Co giat, mat y thi\n\n" .
            "Tuong tac nguy hiem can tranh:\n" .
            "- Warfarin + Aspirin: Chay mau nang\n" .
            "- Statin + Nuoc buoi: Tieu co\n" .
            "- MAOI + Thuc pham giau tyramine: Tang huyet ap nguy hiem\n\n" .
            "Luon lam:\n" .
            "- Ke cho bac si tat ca thuoc dang dung\n" .
            "- Doc ky huong dan su dung\n" .
            "- Khong dung thuoc het han\n\n" .
            "So cap cuu: 115\n\n" .
            "Thong tin chi mang tinh tham khao!";
    }
    
    // Diabetes specific
    if (strpos($msg, 'tieu duong') !== false || strpos($msg, 'diabetes') !== false || strpos($msg, 'duong huyet') !== false) {
        return "Tieu duong (Diabetes Mellitus):\n\n" .
            "Trieu chung thuong gap:\n" .
            "- Khat nhieu nuoc\n" .
            "- Di tiểu nhieu lan\n" .
            "- Met moi, sut can\n" .
            "- Mo mat\n" .
            "- Vet thuong lau lanh\n\n" .
            "Phan loai:\n" .
            "- Type 1: Thieu insulin hoan toan (tre em)\n" .
            "- Type 2: Khang insulin (pho bien nhat, ~90%)\n" .
            "- Gestational: Khi mang thai\n\n" .
            "Thuoc dieu tri pho bien:\n" .
            "- Metformin - First-line cho Type 2\n" .
            "- Insulin - Cho Type 1 hoac Type 2 nang\n" .
            "- Glipizide - Kich thich tuyen tuy\n\n" .
            "Can that: Can duoc bac si chuyen khoa theo doi va dieu tri!";
    }
    
    // Blood pressure
    if (strpos($msg, 'huyet ap') !== false || strpos($msg, 'blood pressure') !== false || strpos($msg, 'cao huyet ap') !== false || strpos($msg, 'hypertension') !== false) {
        return "Cao huyet ap (Hypertension):\n\n" .
            "Chi so binh thuong:\n" .
            "- < 120/80 mmHg: Binh thuong\n" .
            "- 120-129/< 80: Cao nhe\n" .
            "- 130-139/80-89: Cao giai doan 1\n" .
            "- >= 140/90: Cao giai doan 2\n\n" .
            "Thuoc dieu tri pho bien:\n" .
            "- Lisinopril (ACEI): Uc che men chuyen\n" .
            "- Amlodipine (CCB): Che kenh calci\n" .
            "- Hydrochlorothiazide (Diuretic): Loi tiểu\n\n" .
            "Luu y quan trong:\n" .
            "- Thuong can dung >= 2 loai thuoc\n" .
            "- Uong thuoc deu dat, khong tu y ngung\n" .
            "- Han che muoi, tap the duc deu dat\n" .
            "- Kiem tra huyet ap thuong xuyen\n\n" .
            "Can that: Cao huyet ap goi la \"ke giet nguoi tham lang\" - can dieu tri suot doi!";
    }
    
    // Default
    return "Cam on cau hoi cua ban!\n\n" .
        "Toi co the giup ban ve:\n" .
        "- Tac dung phu, tuong tac thuoc\n" .
        "- Trieu chung va dieu tri benh\n" .
        "- Cong nghe AI, GNN trong y sinh\n\n" .
        "Vi du cau hoi:\n" .
        "- \"Tac dung phu cua Metformin?\"\n" .
        "- \"Tuong tac thuoc la gi?\"\n" .
        "- \"GNN du doan nhu the nao?\"\n\n" .
        "Hay nhap \"help\" de xem danh sach day du!\n\n" .
        "Can that: Toi la AI tro ly, khong phai bac si. Hay tham khao y kien chuyen gia y te!";
}
?>
