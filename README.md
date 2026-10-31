Here is a clean, structured README/description tailored for your GitHub repository.

---

# Graduate Document CN FileHub (Admin Portal)

**Graduate Document CN FileHub** is a centralized, digital record management system designed for immediate archival, digitization, and tracking of graduate student files. Built specifically for administrative workflows, the portal enables authorized personnel to transition physical cabinet archives into a secure, searchable digital repository.

---

## 🏗️ Tech Stack

* **Frontend / Interface:** PHP with embedded HTML / CSS
* **Database & Management:** SQL (MySQL)
* **Local Server Environment:** Laragon

---

## ✨ Key Features

### 🔐 1. Authentication & Security

* **Role-Based Admin Access:** One account per authorized staff member (`1 Admin = 1 Account`).
* **Registration & Recovery:** Sign-up system requiring Employee Number, Full Name, Email, and Position, bound by integrated Terms & Conditions and Data Privacy Notices.
* **Audit & Activity Tracking:** Unique admin identifiers log all submission, modification, and archiving actions for complete accountability.
* **Session Security:** Strict session handling prevents unauthorized access via browser navigation (`Back`/`Forward` cache prevention) post-logout.

### 📊 2. Dashboard

* **Metrics Overview:** Real-time counters for *Total Archived Documents*, *Total Student Records*, and *Archived Files This Month*.
* **Recent Activity & Quick Actions:** Streamlined shortcuts for searching/archiving records and monitoring recent uploads.
* **Workflow Tools:** Integrated administrative calendar and To-Do list.

### 📁 3. Document Archiving & Uploads

* **Direct Archival System:** Upload scanned physical files straight into the File Management System (FMS).
* **Pre-requisite Record Creation:** Automatically creates structured student profiles prior to document attachment for former students without existing records.
* **Categorized Document Types:**
* Birth Certificate
* Transcript of Records (TOR)
* Curriculum Vitae (CV)
* Recommendation Letter
* PRC PNLE Certification of Board Rating
* Summary of Related Learning Experience (RLE)
* Others



### 🔍 4. Advanced Search & Filtering

* **Targeted Querying:** Locate specific records rapidly using Student Name, Student Number, Tracking ID, Document Type, or Approval Status.
* **Approved File Filtering:** Dedicated administrative filters to isolate verified and approved documents.

---

## 🛠️ Installation & Setup (Laragon)

1. **Clone the repository** into your Laragon `www` directory:
```bash
git clone https://github.com/your-username/graduate-document-cn-filehub.git

```


2. **Database Setup:**
* Open HeidiSQL / phpMyAdmin via Laragon.
* Create a new database (e.g., `cn_filehub`).
* Import the provided `.sql` schema file located in the `/database` directory.


3. **Configuration:**
* Update your database connection credentials in `config.php` (or your designated database config file).


4. **Run Server:**
* Start Apache and MySQL in Laragon.
* Access the portal at `[http://graduate-document-cn-filehub.test](http://graduate-document-cn-filehub.test)` or `
