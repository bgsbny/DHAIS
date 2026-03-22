# Return System Implementation

## Overview
This system handles product returns with intelligent inventory management based on item condition.

## Features

### 1. Return Processing
- **Good Condition**: Items are returned to inventory for resale
- **Bad Condition**: Items are NOT returned to inventory (logged but not restocked)
- Full audit trail with return reasons and conditions

### 2. Database Changes
The following database modifications are required:

#### Tables Modified:
- `purchase_transactions`: Added `transaction_type` and `reference_transaction_id` columns
- `purchase_transaction_details`: Added `return_condition` and `return_reason` columns

#### New Tables:
- `tbl_inventory_movements`: Tracks all inventory movements including returns

### 3. Setup Instructions

1. **Run Database Setup**:
   ```bash
   php add_return_columns.php
   ```

2. **Files Added**:
   - `process_return.php`: Handles return processing logic
   - `add_return_columns.php`: Database setup script

3. **Files Modified**:
   - `transaction_history_details.php`: Updated with return functionality

## How It Works

### Return Process Flow:
1. User selects product to return from transaction details
2. User specifies return quantity and condition (good/bad)
3. User provides return reason
4. System processes return:
   - Creates return transaction with negative amounts
   - If condition is "good": Adds items back to inventory
   - If condition is "bad": Logs return but doesn't restock
   - Records movement in inventory movements table

### Inventory Logic:
- **Good Condition**: `stock_level = stock_level + return_quantity`
- **Bad Condition**: No inventory update, only movement logging

### Transaction Structure:
- Original sale: `transaction_type = 'sale'`
- Return: `transaction_type = 'return'` with `reference_transaction_id` pointing to original
- Negative quantities and amounts for returns

## Usage

1. Navigate to any transaction in Transaction History
2. Click "Refund Product" button
3. Select product, quantity, condition, and reason
4. Submit return

## Benefits

- **Accurate Inventory**: Only good condition items are restocked
- **Full Audit Trail**: All returns are logged with conditions and reasons
- **Financial Accuracy**: Proper negative transactions for returns
- **Condition Tracking**: Clear visibility of item condition on returns

## Technical Details

### Key Functions:
- `process_return.php`: Main return processing logic
- JavaScript in `transaction_history_details.php`: Frontend handling
- Database triggers ensure data integrity

### Error Handling:
- Validates return quantity against original purchase
- Ensures required fields are provided
- Transaction rollback on errors
- User-friendly error messages

### Security:
- Session validation
- Input sanitization
- SQL injection prevention with prepared statements 