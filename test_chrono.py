from datetime import datetime
import re

def check_chronology(text):
    lines = text.split('\n')
    year_blocks = []
    current_year = datetime.now().year
    
    present_keywords = [
        'present', 'présent', 'en cours', 'today', "aujourd'hui", 
        'actualidad', 'ahora', 'actual',
        'н.в.', 'настоящее время', 'по наст. время', 'текущее время', 'по настоящее время'
    ]
    
    # We want to find contiguous blocks or at least valid year ranges.
    # Instead of looking line by line and just appending the max, let's pull all dates.
    dates = []
    for line in lines:
        line_low = line.lower()
        # Find all years like "2020", "2021", etc.
        years = [int(y) for y in re.findall(r"\b(19\d{2}|20\d{2})\b", line)]
        
        has_present = any(kw in line_low for kw in present_keywords)
        if has_present:
            years.append(current_year + 1) # Represent 'present' as next year
            
        if years:
            dates.append((max(years), min(years))) # Store max and min found on the line

    if len(dates) < 2:
        return []

    # Sort dates by appearance in document
    # A standard CV goes from latest to oldest.
    # So dates[0] should be >= dates[1] >= dates[2] ...
    
    out_of_order_count = 0
    # Compare each max date to the next block's max date
    for i in range(len(dates) - 1):
        if dates[i][0] < dates[i+1][0]:
            # If a later block in the CV has a higher year, it's out of order.
            # E.g. dates[0] is 2018, dates[1] is 2020. 
            out_of_order_count += 1
            
    print(f"Dates found: {dates}")
    print(f"Out of order count: {out_of_order_count}")
    return out_of_order_count >= 1

test1 = """
Experience:
Software Engineer
2020 - Present
Junior Developer
2018 - 2020
Intern
2017
"""

test2 = """
Experience:
Intern
2017
Junior Developer
2018 - 2020
Software Engineer
2020 - Present
"""

print("Test 1 (Chronological):", check_chronology(test1))
print("Test 2 (Non-Chronological):", check_chronology(test2))

