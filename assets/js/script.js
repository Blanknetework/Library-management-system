document.getElementById('studentIdForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const studentId = document.getElementById('student_id').value;
    
    try {
        const response = await fetch('check_student.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `student_id=${encodeURIComponent(studentId)}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Store student details and show modal
            Alpine.store('studentDetails', data.student);
            Alpine.data('showConfirmModal', true);
        } else {
            alert(data.message || 'Student not found. Please check your ID.');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
});