# Laravel Developer Test - Document Processing Feature

## Goal

Add a "Document Processing" feature to this Laravel app.
Users upload PDF invoices → AI mock extracts data → Match with mock Purchase Orders → Display on dashboard.

## Setup

1. Clone this repository.
2. Run `composer install`.
3. Copy `.env.example` to `.env` and set up DB.
4. Run: `php artisan migrate --seed` and `php artisan serve`
5. Visit `/dashboard`.

## Mock APIs

-   AI Extraction: `POST /api/mock-ai-extract`
-   Purchase Orders: `GET /api/mock-purchase-orders`

## Requirements

-   PDF upload (validated)
-   Process file via AI mock
-   Match to mock PO
-   Store and display results
-   Use queues for processing
-   Include unit tests
-   Update this README with notes

## Submission

-   Push your code to a public GitHub repo.
-   Email the link before the 48-hour deadline.

Here’s your content reorganized in Markdown while preserving your original tone and flow:

---

## Developer Notes

### Issues Resolved

-   Incorrect controller implementation — used **`AiMockController`** instead of the base controller.
-   API routes were not registered in **`bootstrap/app.php`**, which prevented access to the mock API routes.

---

### Implementation Thought Process

This implementation provides a **document processing feature** with the following story points/steps:

1. **User Upload**
   The user uploads PDF invoices. These invoices are processed in the background by **`AiDocumentProcessor`**, which:

    - Reads the content of the PDF files.
    - Extracts relevant information such as `invoice_number`, `total_amount`, and `vendor`.
    - Compares these details with purchase orders in the database.

2. **Matching Logic**

    - If a match is found (matching `total_amount` and `vendor` fields in purchase orders), the document record is updated with:

        - Status: `processed`
        - Extracted fields: `invoice_number`, `vendor`, `total_amount`, `po_number`.

    - If **no match** is found:

        - An exception is thrown (`Invoice document does not match a purchase order`).
        - The job is retried **3 times** before being marked as failed.
          This assumes the purchase order might not exist initially but could be added later.

3. **Routes Provided**

    - **POST** `{{API_BASE_URL}}/mock-ai-extract/bulk`
      Processes multiple files (max 10) as background jobs.
      Immediately returns a “processing” message. Document records are created with:

        - `file_name` (location of file to be processed)
        - `status` set to `pending`

    - **POST** `{{API_BASE_URL}}/mock-ai-extract`
      Processes a single file:

        - Attempts synchronous processing (3-second timeout).
        - If processed in time, returns the processed document and details.
        - If not, queues it for background processing and returns `pending` status.

    - **GET** `{{API_BASE_URL}}/mock-purchase-orders`
      Retrieves purchase order details.
    - **WEB** `{{WEB_BASE_URL}}/dashboard`
      Displays results of processed or pending invoice documents.

4. **Variables**

    - `API_BASE_URL`: `http://127.0.0.1:8000/api`
    - `WEB_BASE_URL`: `http://127.0.0.1:8000`
      _(This assumes local development environemnt, and the APP_PORT set to 8000, please adjust accordingly if there is assumption is incorrect)_

5. **Testing**

    - Application can be tested locally via Postman.

---

### Technical Highlights

-   **Standard API responses** via `APIResponse` Trait.
-   **Invoice Details DTO** for schema validation of AI model responses.
-   **`AIMockController`** contains entry logic for document processing.
-   **`DocumentStatus`** enum contains all the possible status values of the document [pending, processed, failed, abandoned].
-   **`ProcessDocument`** job handles background processing.
-   **`AiDocumentProcessor`** service encapsulates core extraction logic.
-   **`documents`** this is the name of the queue used to process documents in the background. To process the documents locally run `php artisan queue:work --queue=documents`
-   Basic test coverage in `Tests` folder.
-   Files remain stored on the server post-processing (pending requirements for post-processing cleanup are provided via business requirements).

---

### Assumptions

-   Each invoice can only match **one** purchase order.
    _(If multiple matches occur, additional details — e.g., `dateOfCreation`, `po_number` — may be required.)_
-   Uploaded PDF documents are **not encrypted**.

---

### Areas of Improvement / Considerations

-   Deploy separate **worker servers** for background job processing, with the primary server handling requests.
-   Implement an **observability stack** (Prometheus, Grafana, Loki, Promtail) for logs, metrics, and traces.
-   Send notifications:

    -   When failed document jobs exceed a set threshold.
    -   When `AiDocumentProcessor` fails to receive a response from the AI model.

-   Handle multiple purchase order matches by duplicating or updating document records (dependent on business rules).
-   Update dashboard in real-time via:

    -   Polling, or
    -   Server-Sent Events / WebSockets.

-   Automatically restart workers in production/staging after deployments or crashes.
-   Ensure job retries(This is implemented):

    -   At least 3 attempts before marking as failed.
    -   Use **exponential backoff**.

-   Prevent **double processing** when multiple servers are involved(This is implemented):

    -   Use DB transactions & locking.

-   Notify users when:

    -   All bulk-uploaded documents finish processing.
    -   A single uploaded document finishes processing.

-   Consider how to handle **encrypted PDFs**. (dependent on business rules)
-   Better decriptive log messages
-   Apply rate-limiting on extraction routes to prevent abuse.
-   Further improvements possible but require business requirement discussions.
