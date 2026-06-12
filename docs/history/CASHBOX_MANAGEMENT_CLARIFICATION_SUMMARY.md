# Cashbox Management Clarification

## 1. What is a "Cashbox" (صندوق)?
In the Tekrit Municipality accounting module, a **Cashbox** represents a physical or digital location where money is held. Because the municipality operates strictly with multiple currencies that cannot be mixed, separate cashboxes must exist for each currency.

### Examples of Cashboxes:
- **صندوق البلدية - ليرة لبنانية** (Municipality Cashbox - LBP)
- **صندوق البلدية - دولار أمريكي** (Municipality Cashbox - USD)
- **حساب البنك - ليرة لبنانية** (Bank Account - LBP)

## 2. Database Structure (`accounting_cashboxes`)
The cashboxes are stored natively in the database table `accounting_cashboxes`. The schema works as follows:
- **`currency_id`**: A strict link to the `currencies` table. This forces the cashbox to hold only one type of currency.
- **`opening_balance`**: The starting balance of the cashbox on the day the municipality begins using the digital system (e.g., the money physically in the safe on Day 1).
- **`current_balance`**: The live, up-to-the-second balance. Whenever an income or expense is recorded in `accounting_treasury.php`, this exact column mathematically goes up or down.

## 3. UI Management Status
**Current State:**
- The default cashboxes (LBP and USD) were created automatically in the database during the Phase 6B setup.
- Currently, there is **no user interface** for the admin to manage or add new cashboxes. The system currently relies on the default cashboxes.

**Future Improvement Recommendation:**
To make the system fully dynamic, a simple **"إدارة الصناديق" (Cashbox Management)** page should be built in the future. This page would allow the municipality admin to:
1. Create a new cashbox.
2. Assign a strict currency to the new cashbox.
3. Set the `opening_balance`.
4. Edit notes or descriptions.
5. Activate or deactivate cashboxes that are no longer used.
6. View the exact `current_balance` of all cashboxes from a high level.

*Note: Per instructions, this management UI has not been built yet. It is documented here as a clear future enhancement.*
