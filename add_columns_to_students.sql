-- Script to add contact_number, address, status, and created_at columns to students table
-- First check if columns already exist to avoid errors

DECLARE
  contact_exists NUMBER;
  address_exists NUMBER;
  status_exists NUMBER;
  created_at_exists NUMBER;
BEGIN
  -- Check if contact_number column exists
  SELECT COUNT(*) INTO contact_exists
  FROM USER_TAB_COLUMNS
  WHERE TABLE_NAME = 'STUDENTS'
  AND COLUMN_NAME = 'CONTACT_NUMBER';
  
  -- Check if address column exists
  SELECT COUNT(*) INTO address_exists
  FROM USER_TAB_COLUMNS
  WHERE TABLE_NAME = 'STUDENTS'
  AND COLUMN_NAME = 'ADDRESS';
  
  -- Check if status column exists
  SELECT COUNT(*) INTO status_exists
  FROM USER_TAB_COLUMNS
  WHERE TABLE_NAME = 'STUDENTS'
  AND COLUMN_NAME = 'STATUS';
  
  -- Check if created_at column exists
  SELECT COUNT(*) INTO created_at_exists
  FROM USER_TAB_COLUMNS
  WHERE TABLE_NAME = 'STUDENTS'
  AND COLUMN_NAME = 'CREATED_AT';
  
  -- Add contact_number column if it doesn't exist
  IF contact_exists = 0 THEN
    EXECUTE IMMEDIATE 'ALTER TABLE sys.students ADD contact_number VARCHAR2(20)';
    EXECUTE IMMEDIATE 'COMMENT ON COLUMN sys.students.contact_number IS ''Student contact number (e.g., 09123456789)''';
    DBMS_OUTPUT.PUT_LINE('Added contact_number column to students table');
  ELSE
    DBMS_OUTPUT.PUT_LINE('contact_number column already exists');
  END IF;
  
  -- Add address column if it doesn't exist
  IF address_exists = 0 THEN
    EXECUTE IMMEDIATE 'ALTER TABLE sys.students ADD address VARCHAR2(255)';
    EXECUTE IMMEDIATE 'COMMENT ON COLUMN sys.students.address IS ''Student complete address''';
    DBMS_OUTPUT.PUT_LINE('Added address column to students table');
  ELSE
    DBMS_OUTPUT.PUT_LINE('address column already exists');
  END IF;
  
  -- Add status column if it doesn't exist
  IF status_exists = 0 THEN
    EXECUTE IMMEDIATE 'ALTER TABLE sys.students ADD status VARCHAR2(20) DEFAULT ''active''';
    EXECUTE IMMEDIATE 'COMMENT ON COLUMN sys.students.status IS ''Student account status (active, inactive, suspended)''';
    DBMS_OUTPUT.PUT_LINE('Added status column to students table');
  ELSE
    DBMS_OUTPUT.PUT_LINE('status column already exists');
  END IF;
  
  -- Add created_at column if it doesn't exist
  IF created_at_exists = 0 THEN
    EXECUTE IMMEDIATE 'ALTER TABLE sys.students ADD created_at DATE DEFAULT SYSDATE';
    EXECUTE IMMEDIATE 'COMMENT ON COLUMN sys.students.created_at IS ''Date when the student record was created''';
    DBMS_OUTPUT.PUT_LINE('Added created_at column to students table');
  ELSE
    DBMS_OUTPUT.PUT_LINE('created_at column already exists');
  END IF;
  
  COMMIT;
END;
/ 