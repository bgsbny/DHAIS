<div class="container mt-5">
  <h3>Return Items</h3>

  <!-- Original Transaction Info -->
  <div class="card p-3 mb-3">
    <h5>Transaction #101 - Juan Dela Cruz</h5>
    <p><strong>Date:</strong> 2025-08-01</p>
    <p><strong>Total Amount:</strong> ₱1,000.00</p>
  </div>

  <!-- Items Purchased -->
  <form action="process_return.php" method="POST">
    <input type="hidden" name="original_transaction_id" value="101">
    
    <table class="table table-bordered">
      <thead class="table-light">
        <tr>
          <th>Select</th>
          <th>Product</th>
          <th>Quantity Purchased</th>
          <th>Quantity to Return</th>
          <th>Unit Price</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><input type="checkbox" name="return_items[0][selected]"></td>
          <td>Brake Pad</td>
          <td>2</td>
          <td>
            <input type="number" name="return_items[0][quantity]" class="form-control" value="1" min="1" max="2">
            <input type="hidden" name="return_items[0][product_id]" value="1001">
            <input type="hidden" name="return_items[0][unit_price]" value="500">
          </td>
          <td>₱500.00</td>
        </tr>
        <!-- Repeat for other items -->
      </tbody>
    </table>

    <!-- Reason for return -->
    <div class="mb-3">
      <label for="return_reason" class="form-label">Reason for Return</label>
      <select name="return_reason" id="return_reason" class="form-select" required>
        <option value="">Select reason</option>
        <option value="Defective">Defective</option>
        <option value="Wrong item">Wrong item delivered</option>
        <option value="Customer changed mind">Customer changed mind</option>
      </select>
    </div>

    <button type="submit" class="btn btn-danger">Return Selected Item(s)</button>
  </form>
</div>
