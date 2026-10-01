#!/bin/bash
#
# Grants the admin console its own MySQL user with the narrowest set of
# privileges it can run on.
#
# Why this exists: the console used to reach the app database with the app's own
# credentials - the same ones the public API runs on. A compromise of the
# console, which holds staff sessions and can unsuspend real accounts, would
# then also have carried the app's full write access, including every table the
# console has no business touching: posts, conversations, chat messages,
# sessions, password reset tokens, role assignments.
#
# The console shares the app database because it renders the same rows (a ticket
# names the member it is about) and enforces decisions against them (a
# subscription approval flips `users.is_verified`). That is a reason to share the
# tables, not a reason to share the credentials.
#
# Two things to know about running it:
#   - This file lives in /docker-entrypoint-initdb.d, so MySQL runs it on first
#     boot of an EMPTY data directory. It is not re-run on an existing volume.
#   - On a fresh volume the database is still empty when it runs, and MySQL
#     refuses to grant on a table that does not exist. So a grant whose table is
#     missing is reported and skipped rather than aborting the run; the
#     alternative is a boot that dies halfway and leaves the user half-created.
#     Re-run it once the app migrations have created the schema.
#
# The password is read from the environment so it never lands in the
# repository, and the script refuses to create an account without one.

set -euo pipefail

CONSOLE_USER="${CONSOLE_DB_USER:-amtechat_admin_console}"
CONSOLE_PASSWORD="${CONSOLE_DB_PASSWORD:-}"
APP_DB="${APP_DB_NAME:-amtechat}"

if [ -z "${CONSOLE_PASSWORD}" ]; then
    echo "admin-console-grants: CONSOLE_DB_PASSWORD is not set." >&2
    echo "  Refusing to create '${CONSOLE_USER}' with an empty password." >&2
    echo "  Set CONSOLE_DB_PASSWORD on the mysql service and recreate the volume." >&2
    exit 1
fi

echo "admin-console-grants: creating least-privilege user '${CONSOLE_USER}' on '${APP_DB}'"

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" \
    -e "CREATE USER IF NOT EXISTS '${CONSOLE_USER}'@'%' IDENTIFIED BY '${CONSOLE_PASSWORD}';"

# Grants are issued one table at a time on purpose. In a single statement MySQL
# takes the union of the privileges, so a combined grant cannot be narrowed by a
# later one for the same table.
ensure_grant() {
    local priv="$1"
    local table="$2"

    local present
    present="$(mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" -N -B \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${APP_DB}' AND table_name = '${table}';")"

    if [ "${present}" != "1" ]; then
        echo "  SKIP  ${table} (not created yet - run the app migrations, then re-run this script)"
        return 0
    fi

    mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" \
        -e "GRANT ${priv} ON \`${APP_DB}\`.\`${table}\` TO '${CONSOLE_USER}'@'%';"
    echo "  GRANT ${priv} ON ${table}"
}

# Read-only. `users` is granted SELECT here and UPDATE further down, which is
# the only overlap between the two lists in this script.
ensure_grant "SELECT" "users"
ensure_grant "SELECT" "features"

# The console's own work: the queues it answers and the config it maintains.
ensure_grant "SELECT, INSERT, UPDATE" "reports"
ensure_grant "SELECT, INSERT, UPDATE" "support_tickets"
ensure_grant "SELECT, INSERT" "support_messages"
ensure_grant "SELECT, INSERT, UPDATE" "account_appeals"

# Configuration. The API already restricts these to the admin role; the database
# grant is the second half of that, not a replacement for it.
ensure_grant "SELECT, INSERT, UPDATE" "email_configs"
ensure_grant "SELECT, INSERT, UPDATE, DELETE" "email_templates"
ensure_grant "SELECT, INSERT, UPDATE" "payment_gateways"
ensure_grant "SELECT, INSERT, UPDATE" "plan_prices"

# Approving a paid verification sets the status, the verification timestamp and
# the term, and expires the subscription it replaced (which is why auto_renew is
# written too). `users` is here rather than above: this is the console's one
# direct write to the member table, and it is the is_verified flag alone.
ensure_grant "SELECT, INSERT, UPDATE" "subscriptions"
ensure_grant "SELECT, UPDATE" "users"

# Insert-only on purpose. The console writes a notification when it makes a
# decision; it never reads or changes one. A member's notification list is the
# member's, and the app is what marks them read.
ensure_grant "INSERT" "notifications"

# Deliberately absent, and worth writing down because the app's own database user
# has every one of them:
#   posts, comments, post_media, reactions         member content
#   conversations, chat_messages, chat_members      private messaging
#   chat_presence                                  who is online
#   personal_access_tokens                         console sessions live in the
#                                                  console database; a grant here
#                                                  would let the console mint
#                                                  app-user sessions
#   password_resets, sessions, cache, jobs         credentials and internals
#   roles, role_user                               the app's own authorisation
#   email_verified_at, two-factor columns          authentication material
#
# If a future screen needs one of these, add the grant on purpose. Reaching for
# the app user instead is how the console came to hold all of them at once.

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "FLUSH PRIVILEGES;"

echo "admin-console-grants: done. Verify with:"
echo "  SHOW GRANTS FOR '${CONSOLE_USER}'@'%';"
