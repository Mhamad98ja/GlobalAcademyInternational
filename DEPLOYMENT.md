# Free test deployment

This project is prepared for a Render Blueprint deployment. It creates a free `onrender.com` web address and a PostgreSQL database.

## Deploy

1. Push the latest `main` branch to GitHub.
2. Create a free [Render](https://render.com) account with GitHub.
3. In Render, choose **New > Blueprint**, select `Mhamad98ja/SBM`, and create the Blueprint.
4. When prompted, provide the administrator name, email, and a strong password. The test address will be `https://sbm-mhamad98ja.onrender.com`.
5. Wait for the build to complete, then open the service URL and sign in with the account entered in step 4.

The initial database contains only roles/permissions, one administrator, academic years 2026-2027 and 2027-2028, Preschool/KG1/KG2, GR1-GR12, and sections A-D. It does not include demo students, books, suppliers, or transactions.

## Important free-plan limits

- The web service sleeps after 15 minutes without traffic; the next visit can take about a minute to wake it.
- The free Render PostgreSQL database is intended for testing and expires after 30 days. Export or upgrade it before then if you need to keep data.
- Render's free web service cannot send email over SMTP ports. Configure a paid host or an HTTPS email API before using email password resets in production.
