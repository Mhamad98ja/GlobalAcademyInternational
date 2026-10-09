# Fresh free test deployment

This project is prepared for a clean Render Blueprint deployment. It creates a new free `onrender.com` web address and a PostgreSQL database, without using the old `sbm-vl24` service.

## Deploy

1. Create a free [Render](https://render.com) account with GitHub.
2. Because this repository is private, install the [Render GitHub App](https://github.com/apps/render/installations/new) and grant it access to `Mhamad98ja/GlobalAcademyInternational`.
3. Open the deployment link below:

   [![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy?repo=https://github.com/Mhamad98ja/GlobalAcademyInternational)

4. Select **Create all as new services**. Render will create `sbm-school-book-test` and its `sbm-school-book-test-db` PostgreSQL database together.
5. Provide the requested administrator name, email, and password. Use `Password123@` only for testing; choose a stronger password for a real school.
6. Wait for the build to complete, open the generated service URL, and sign in with that administrator account.

Do not associate the old `sbm-vl24` service with this new Blueprint. It has no database connection.

The initial database contains only roles/permissions, one administrator, academic years 2026-2027 and 2027-2028, Preschool/KG1/KG2, GR1-GR12, and sections A-D. It does not include demo students, books, suppliers, or transactions.

## Important free-plan limits

- The web service sleeps after 15 minutes without traffic; the next visit can take about a minute to wake it.
- The free Render PostgreSQL database is intended for testing and expires after 30 days. Export or upgrade it before then if you need to keep data.
- Render's free web service cannot send email over SMTP ports. This project supports the Resend HTTPS API for password-reset emails. In the web service's **Environment** settings, add `RESEND_API_KEY`, set `MAIL_MAILER=resend`, and set `MAIL_FROM_ADDRESS` to a sender address verified in Resend. Then save the variables and manually deploy the latest commit. Any active user account can request a reset link at `/forgot-password`.
